<?php

namespace App\Http\Controllers\payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\customer\{CheckoutCartController, CheckoutPreorderController};
use Illuminate\Support\Facades\{Auth, Log, Http};
use App\Models\{CheckoutDraft};
use Exception;

class PaymentController extends Controller
{
  // This method is for multi-payment popups and is correct.
  public function success(Request $request, $type)
  {
    try {
      $controller = $this->getCheckoutControllerForType($type);
      $controller->processRemainingDrafts(Auth::id());
    } catch (Exception $e) {
      Log::error("Multi-flow ($type): Failed post-payment success processing", ['error' => $e->getMessage()]);
    }
    return view('payment.simple-message', ['title' => 'Payment Received!', 'message' => 'Payment successful. This window will close automatically.']);
  }

  // This method is for multi-payment popups and is correct.
  public function failed(Request $request, $type, $draft_id = null)
  {
    if ($draft_id) {
      $cancelled = $request->session()->get('flow_cancelled_drafts', []);
      $cancelled[] = $draft_id;
      $request->session()->put('flow_cancelled_drafts', array_unique($cancelled));
      Log::info("Multi-flow ($type): Added draft #{$draft_id} to session cancelled list.", ['cancelled_list' => array_unique($cancelled)]);
    }
    return view('payment.simple-message', ['title' => 'Payment Cancelled', 'message' => 'You have cancelled this payment. This window will close automatically.']);
  }

  // --- METHODS FOR THE SINGLE PAYMENT DIRECT REDIRECT FLOW ---

  public function singlePaymentSuccess(Request $request)
  {
    $user = Auth::user();
    $type = session('checkout_flow_type', 'cart'); // Get flow type from session
    $sourceRoute = $this->getSourceRoute($type, 'orders.index');
    $wasMixedPayment = session('has_mixed_payment', false);
    $draftIdsInFlow = session('checkout_flow_draft_ids', []);

    try {
      $controller = $this->getCheckoutControllerForType($type);
      $controller->processRemainingDrafts($user->user_id);

      $onlineDraft = CheckoutDraft::whereIn('checkout_draft_id', $draftIdsInFlow)
        ->where('user_id', $user->user_id)->where('is_cod', false)->first();

      if ($onlineDraft && !$onlineDraft->processed_at) {
        $controller->processOnlineOrderFromDraft($onlineDraft);
      }

      $processedDrafts = CheckoutDraft::whereIn('checkout_draft_id', $draftIdsInFlow)
        ->where('user_id', $user->user_id)->whereNotNull('processed_at')->get();

      if ($processedDrafts->isNotEmpty()) {
        $this->removeProcessedItemsFromSession($type, $processedDrafts);
      }
    } catch (Exception $e) {
      Log::error("Single-flow ($type): Error during success processing.", ['error' => $e->getMessage()]);
      $this->clearFlowSession();
      return redirect()->route($sourceRoute)->with('error', 'Payment was successful, but a processing error occurred.');
    }

    $message = $wasMixedPayment
      ? 'Your online payment was successful and your COD order(s) have also been placed.'
      : 'Your payment was successful and your order has been placed.';

    $this->clearFlowSession();
    return redirect()->route($sourceRoute)->with('success', $message);
  }

  public function singlePaymentFailed(Request $request, $draft_id = null)
  {
    $user = Auth::user();
    $type = session('checkout_flow_type', 'cart');
    $sourceRoute = $this->getSourceRoute($type);
    $ordersRoute = $this->getSourceRoute($type, 'orders.index');
    $draftIdsInFlow = session('checkout_flow_draft_ids', []);

    $this->clearFlowSession();

    try {
      if ($draft_id) {
        $cancelledDraft = CheckoutDraft::where('checkout_draft_id', $draft_id)->where('user_id', $user->user_id)->first();
        if ($cancelledDraft) {
          $this->expireSessionsAndRestoreItems($type, collect([$cancelledDraft]));
        }
      }

      $processedDraftsCount = CheckoutDraft::whereIn('checkout_draft_id', $draftIdsInFlow)
        ->where('checkout_draft_id', '!=', $draft_id)
        ->whereNotNull('processed_at')->count();

      if ($processedDraftsCount > 0) {
        return redirect()->route($ordersRoute)->with('info', 'Your online payment was cancelled, but your other successful orders have been placed.');
      } else {
        $message = $type === 'preorder' ? 'Your payment was cancelled and items have been returned to your preorder list.' : 'Your payment was cancelled and items have been returned to your cart.';
        return redirect()->route($sourceRoute)->with('info', $message);
      }
    } catch (Exception $e) {
      Log::error("Single-flow ($type): Error in failed redirect.", ['error' => $e->getMessage()]);
      return redirect()->route($sourceRoute)->with('error', 'An error occurred while cancelling your payment. Please check your cart/preorder list.');
    }
  }

  /**
   * Finalizes the flow for MULTIPLE online payments.
   */
  public function finalizeFlow(Request $request)
  {
    $user = $request->user();
    $type = session('checkout_flow_type', 'cart');
    $sourceRoute = $this->getSourceRoute($type);
    $ordersRoute = $this->getSourceRoute($type, 'orders.index');
    $draftIdsInFlow = session('checkout_flow_draft_ids', []);
    $cancelledDraftIds = session('flow_cancelled_drafts', []);

    $this->clearFlowSession();

    if (empty($draftIdsInFlow)) {
      return redirect()->route($sourceRoute)->with('info', 'Your checkout session has already been completed.');
    }

    try {
      $draftsInFlow = CheckoutDraft::whereIn('checkout_draft_id', $draftIdsInFlow)->where('user_id', $user->user_id)->get();

      $explicitlyCancelled = $draftsInFlow->whereIn('checkout_draft_id', $cancelledDraftIds);
      $abandoned = $draftsInFlow->whereNull('processed_at')->whereNotIn('checkout_draft_id', $cancelledDraftIds)->where('is_cod', false);
      $allToCancel = $explicitlyCancelled->merge($abandoned);

      if ($allToCancel->isNotEmpty()) {
        $this->expireSessionsAndRestoreItems($type, $allToCancel);
      }

      $allProcessedDrafts = $draftsInFlow->whereNotNull('processed_at');

      if ($allProcessedDrafts->isNotEmpty()) {
        $this->removeProcessedItemsFromSession($type, $allProcessedDrafts);
      }

      if ($allProcessedDrafts->isNotEmpty()) {
        $message = "Your checkout is complete. {$allProcessedDrafts->count()} order(s) placed successfully.";
        if ($allToCancel->isNotEmpty()) {
          $message .= " {$allToCancel->count()} order(s) cancelled.";
        }
        return redirect()->route($ordersRoute)->with('success', $message);
      } else {
        $message = $type === 'preorder' ? 'All pending preorders were cancelled and items have been returned to your list.' : 'All pending orders were cancelled and items have been returned to your cart.';
        return redirect()->route($sourceRoute)->with('info', $message);
      }
    } catch (Exception $e) {
      Log::error("CRITICAL ERROR in finalizeFlow ($type).", ['user_id' => $user->id, 'error' => $e->getMessage()]);
      return redirect()->route($sourceRoute)->with('error', 'An unexpected error occurred. Please check your cart/preorder list and order history.');
    }
  }

  // --- SHARED HELPER METHODS ---

  private function getCheckoutControllerForType(string $type)
  {
    return match ($type) {
      'preorder' => app(CheckoutPreorderController::class),
      default => app(CheckoutCartController::class),
    };
  }

  private function getSourceRoute(string $type, string $page = 'main')
  {
    if ($page === 'orders.index') {
      return 'customer.orders.index';
    }
    return $type === 'preorder' ? 'customer.preorder' : 'customer.cart';
  }

  private function clearFlowSession()
  {
    session()->forget(['checkout_flow_draft_ids', 'has_mixed_payment', 'flow_cancelled_drafts', 'checkout_flow_type']);
  }

  private function expireSessionsAndRestoreItems(string $type, $drafts)
  {
    if ($drafts->isEmpty()) return;

    $itemsToRestore = [];
    foreach ($drafts as $draft) {
      if ($draft->transaction_id) {
        try {
          Http::withHeaders(['accept' => 'application/json', 'authorization' => 'Basic ' . base64_encode(config('services.paymongo.secret') . ':')])
            ->post("https://api.paymongo.com/v1/checkout_sessions/{$draft->transaction_id}/expire");
        } catch (Exception $e) { /* Fail silently */
        }
      }
      $itemsToRestore = array_merge($itemsToRestore, $draft->cart);
    }

    $sessionKey = $type === 'preorder' ? 'preorder' : 'cart';
    $currentItems = collect(session($sessionKey, []));
    $restoredItems = $currentItems->concat($itemsToRestore)->unique('product_id')->values()->all();
    session([$sessionKey => $restoredItems]);
    CheckoutDraft::whereIn('checkout_draft_id', $drafts->pluck('checkout_draft_id'))->delete();
  }

  private function removeProcessedItemsFromSession(string $type, $processedDrafts)
  {
    $controller = $this->getCheckoutControllerForType($type);
    if ($type === 'preorder') {
      $controller->removeProcessedItemsFromPreorder($processedDrafts);
    } else {
      $controller->removeProcessedItemsFromCart($processedDrafts);
    }
  }


  // In PaymentController.php

  public function preorderSuccess(Request $request)
  {
    $user = Auth::user();
    $draftIdsInFlow = session('checkout_flow_draft_ids', []);

    // If there's no draft in session, nothing to do — treat as generic success.
    if (empty($draftIdsInFlow)) {
      return redirect()->route('customer.orders.index')->with('success', 'Your preorder has been placed successfully.');
    }

    try {
      // Find the first online (non-COD) draft in the flow for this user
      $onlineDraft = \App\Models\CheckoutDraft::whereIn('checkout_draft_id', $draftIdsInFlow)
        ->where('user_id', $user->user_id)
        ->where('is_cod', false)
        ->first();

      // If there's no online draft, clear and return (maybe only CODs were in flow)
      if (!$onlineDraft) {
        $this->clearFlowSession();
        return redirect()->route('customer.orders.index')->with('info', 'Your payment was processed.');
      }

      // 1) Prefer webhook-created PaymentDetail (if your webhook already ran)
      $paymentDetail = \App\Models\PaymentDetail::where('transaction_id', $onlineDraft->transaction_id)
        ->with('order.preorderDetail')
        ->first();

      if ($paymentDetail && $paymentDetail->order) {
        // Webhook created the order already — use that
        $order = $paymentDetail->order;

        // Clean up session and remove processed preorder items
        $this->removeProcessedItemsFromSession('preorder', collect([$onlineDraft]));
        $this->clearFlowSession();

        // Redirect to the upload step so user can upload receipt (if needed)
        return redirect()->route('checkout.preorder.proceed', ['business_id' => $order->business_id])
          ->with('payment_successful', true)
          ->with('order_id', $order->order_id);
      }

      // 2) No PaymentDetail found — assume redirect-only flow. Create records from draft.
      // Use the CheckoutPreorderController's processOnlineOrderFromDraft() to perform DB creation.
      $preorderController = app(\App\Http\Controllers\customer\CheckoutPreorderController::class);

      // Re-load the draft to ensure fresh model (and to avoid stale attributes)
      $draft = \App\Models\CheckoutDraft::where('checkout_draft_id', $onlineDraft->checkout_draft_id)
        ->where('user_id', $user->user_id)
        ->first();

      if (!$draft) {
        // Draft unexpectedly missing
        Log::warning("preorderSuccess: draft not found during redirect", ['draft_ids' => $draftIdsInFlow]);
        $this->clearFlowSession();
        return redirect()->route('customer.preorder')->with('error', 'Could not find your preorder. Please check your preorder list.');
      }

      if (!$draft->processed_at) {
        // Create the Order / PreOrder / PaymentDetail, mark draft processed
        $preorderController->processOnlineOrderFromDraft($draft);
      } else {
        Log::info('preorderSuccess: draft already marked processed', ['draft_id' => $draft->checkout_draft_id]);
      }

      // Now the order should exist (created above). Attempt to fetch it
      $paymentDetail = \App\Models\PaymentDetail::where('transaction_id', $draft->transaction_id)->with('order.preorderDetail')->first();

      if ($paymentDetail && $paymentDetail->order) {
        $order = $paymentDetail->order;

        // Clean up session / remove processed preorder items
        $this->removeProcessedItemsFromSession('preorder', collect([$draft]));
        $this->clearFlowSession();

        return redirect()->route('checkout.preorder.proceed', ['business_id' => $order->business_id])
          ->with('payment_successful', true)
          ->with('order_id', $order->order_id);
      }

      // Fallback: created order not found for some reason — still clear session and show success
      Log::warning("preorderSuccess fallback: no order found after processing draft", ['draft_id' => $draft->checkout_draft_id]);
      $this->removeProcessedItemsFromSession('preorder', collect([$draft]));
      $this->clearFlowSession();
      return redirect()->route('customer.orders.index')->with('success', 'Your payment was successful and your preorder has been placed.');
    } catch (\Exception $e) {
      Log::error("Preorder success redirect failed.", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      $this->clearFlowSession();
      return redirect()->route('customer.orders.index')->with('error', 'An error occurred, but your payment was successful.');
    }
  }


  // ADD THIS NEW METHOD FOR PREORDER FAILURE
  // In PaymentController.php

  public function preorderFailed(Request $request, $draft_id = null)
  {
    $user = Auth::user();
    $sourceRoute = $this->getSourceRoute('preorder');
    $this->clearFlowSession(); // Clear flow-specific session data

    try {
      if ($draft_id) {
        $cancelledDraft = \App\Models\CheckoutDraft::where('checkout_draft_id', $draft_id)
          ->where('user_id', $user->user_id)->first();

        if ($cancelledDraft) {
          // --- MODIFICATION START: Clean the transaction_id from the main session ---
          $transactionIdToClear = $cancelledDraft->transaction_id;

          if ($transactionIdToClear) {
            $currentPreorders = collect(session('preorder', []));
            $cleanedPreorders = $currentPreorders->map(function ($item) use ($transactionIdToClear) {
              // If the item has a transaction_id that matches the cancelled one, unset it.
              if (isset($item['transaction_id']) && $item['transaction_id'] === $transactionIdToClear) {
                unset($item['transaction_id']);
              }
              return $item;
            })->all();

            // Save the cleaned array back to the session.
            session(['preorder' => $cleanedPreorders]);
            Log::info('Cleared transaction_id from session for cancelled preorder.', ['transaction_id' => $transactionIdToClear]);
          }
          // --- MODIFICATION END ---

          // Now, delete the draft record from the database as it's no longer needed.
          $cancelledDraft->delete();
        }
      }
    } catch (Exception $e) {
      Log::error("Preorder failed redirect cleanup failed.", ['error' => $e->getMessage()]);
      return redirect()->route($sourceRoute)->with('error', 'An error occurred. Please check your preorder list.');
    }

    return redirect()->route($sourceRoute)->with('info', 'Your payment was cancelled. The items are still in your preorder list.');
  }
}
