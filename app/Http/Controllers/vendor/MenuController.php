<?php

namespace App\Http\Controllers\vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\{Log, DB, Auth, Storage};
use App\Models\Product;

class MenuController extends Controller
{
  /**
   * Sync dietary specifications with a product.
   */
  private function syncDietarySpecifications(int $productId, array $dietInput): void
  {
    Log::info('Syncing dietary specifications', [
      'product_id' => $productId,
      'diet_input' => $dietInput
    ]);

    if (empty($dietInput)) {
      Product::find($productId)?->dietarySpecifications()->sync([]);
      Log::info('No dietary specifications provided — cleared all for product', [
        'product_id' => $productId
      ]);
      return;
    }

    $specIds = DB::table('dietary_specifications')
      ->whereIn('dietary_specification_id', $dietInput)
      ->pluck('dietary_specification_id')
      ->toArray();

    Log::info('Matched dietary specs by ID', [
      'product_id' => $productId,
      'matched_ids' => $specIds
    ]);

    if (empty($specIds)) {
      $specIds = DB::table('dietary_specifications')
        ->whereIn('dietary_spec_name', $dietInput)
        ->pluck('dietary_specification_id')
        ->toArray();

      Log::info('Matched dietary specs by name', [
        'product_id' => $productId,
        'matched_ids' => $specIds
      ]);
    }

    if (!empty($specIds)) {
      Product::find($productId)?->dietarySpecifications()->sync($specIds);
      Log::info('Dietary specifications synced successfully', [
        'product_id' => $productId,
        'final_spec_ids' => $specIds
      ]);
    } else {
      Log::warning('No matching dietary specifications found — nothing synced', [
        'product_id' => $productId,
        'diet_input' => $dietInput
      ]);
    }
  }

  /**
   * Store a new product.
   */
  public function store(Request $request)
  {

    if ($request->hasFile('item_image')) {
      $file = $request->file('item_image');
      Log::info('FILE LOG (PRE-VALIDATION):', [
        'original_name'   => $file->getClientOriginalName(),
        'extension_client' => $file->getClientOriginalExtension(), // From filename
        'mime_type_client' => $file->getClientMimeType(), // Sent by browser (less reliable)
        'mime_type_php'   => $file->getMimeType(), // From php's fileinfo (this is what validation uses)
        'size_kb'         => round($file->getSize() / 1024, 2),
        'is_valid'        => $file->isValid(),
        'error_code'      => $file->getError(), // See https://www.php.net/manual/en/features.file-upload.errors.php
      ]);
    } else {
      Log::warning('STORE METHOD: No file found for "item_image" in the request.');
    }


    // Step 0: Validate input
    $validated = $request->validate([
      'category' => 'required|exists:product_categories,product_category_id',
      'item_name' => 'required|string|max:255',
      'price' => 'required|numeric|min:0',
      'advance_amount' => 'nullable|numeric|min:0',
      'cutoff_hours' => ['nullable', 'integer', 'min:0', 'max:8'],
      'cutoff_minutes' => ['nullable', 'integer', Rule::in([0, 15, 30, 45])],
      'preorder' => 'nullable|in:Yes,No',
      'description' => 'nullable|string|max:1000',
      'item_image' => [
        'required',
        'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/bmp,image/avif',
        'max:2048'
      ],
      'diet' => 'nullable|array',
      'diet.*' => 'string|distinct',
    ]);

    DB::beginTransaction();
    try {
      Log::info('Starting product store process', [
        'user_id' => Auth::id(),
        'business_id' => session('active_business_id'),
        'request_data' => $request->except(['item_image'])
      ]);

      $file = $request->file('item_image');

      // 🔹 Validate file before upload
      if (!$file->isValid()) {
        throw new \Exception('Image upload is invalid: ' . $file->getErrorMessage());
      }

      /**
       * @var \Illuminate\Filesystem\FilesystemAdapter $disk
       */
      $disk = Storage::disk('s3');

      // 🔹 Upload to S3
      $imagePath = $file->store('products', 's3');

      if (!$imagePath) {
        throw new \Exception('Failed to store image on S3. Check AWS permissions or bucket policy.');
      }

      $imageUrl = $disk->url($imagePath);

      // Log both path and URL
      Log::info('S3 upload complete', [
        'stored_path' => $imagePath,
        'public_url'  => $imageUrl
      ]);

      // The logic to combine hours and minutes is the same ---
      $totalCutoffMinutes = null;
      if (isset($validated['cutoff_hours']) || isset($validated['cutoff_minutes'])) {
        $hours = (int)($validated['cutoff_hours'] ?? 0);
        $minutes = (int)($validated['cutoff_minutes'] ?? 0);
        $totalCutoffMinutes = ($hours * 60) + $minutes;
      }

      // If both were blank or zero, ensure it's saved as NULL
      if ($totalCutoffMinutes === 0) {
        $totalCutoffMinutes = null;
      }

      // Step 2: Insert product
      $productId = DB::table('products')->insertGetId([
        'business_id'         => session('active_business_id'),
        'product_category_id' => $validated['category'],
        'item_name'           => $validated['item_name'],
        'price'               => $validated['price'],
        'advance_amount'      => (($validated['preorder'] ?? 'No') === 'Yes')
          ? ($validated['advance_amount'] ?? 0.00)
          : 0.00,
        'cutoff_minutes'      => $totalCutoffMinutes,
        'is_available'        => 1,
        'is_pre_order'        => ($validated['preorder'] ?? 'No') === 'Yes',
        'image_url'           => $imageUrl,
        'description'         => $validated['description'] ?? null,
        'created_at'          => now(),
      ]);

      Log::info('DB insert for new product', [
        'product_id' => $productId,
        'saved_image_url' => $imageUrl
      ]);

      // Step 3: Attach dietary specs
      $this->syncDietarySpecifications($productId, $validated['diet'] ?? []);

      DB::commit();

      return redirect()->back()->with('success', 'Product added successfully!');
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Failed to store product', [
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      // TO THIS (also adding withInput() to keep form data)
      return redirect()->back()->with('error', 'Failed to save product: ' . $e->getMessage())->withInput();
    }
  }

  /**
   * Update an existing product.
   */
  public function update(Request $request, $id)
  {
    session(['active_role' => 'vendor']);

    Log::info('Incoming update request', ['request_data' => $request->all()]);

    if ($request->hasFile('item_image')) {
      $file = $request->file('item_image');
      Log::info('Debug: Uploaded file info', [
        'original_name' => $file->getClientOriginalName(),
        'mime_type' => $file->getMimeType(),
        'extension' => $file->getClientOriginalExtension(),
        'size_kb' => $file->getSize() / 1024,
        'is_valid' => $file->isValid(),
        'error_code' => $file->getError(),
      ]);
    }

    // Step 0: Validate input
    $validated = $request->validate([
      'category' => 'required|exists:product_categories,product_category_id',
      'item_name' => 'required|string|max:255',
      'price' => 'required|numeric|min:0',
      'advance_amount' => 'nullable|numeric|min:0',
      'cutoff_hours' => ['nullable', 'integer', 'min:0', 'max:8'],
      'cutoff_minutes' => ['nullable', 'integer', Rule::in([0, 15, 30, 45])],
      'availability' => 'required|in:Available,Not Available',
      'preorder' => 'required|in:Yes,No',
      'description' => 'required|string|max:1000',
      'item_image' => [
        'nullable',
        'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/bmp,image/avif',
        'max:2048'
      ],
      'diet' => 'required|array',
      'diet.*' => 'string|distinct',
    ]);

    // The logic to combine hours and minutes is the same ---
    $totalCutoffMinutes = null;
    if (isset($validated['cutoff_hours']) || isset($validated['cutoff_minutes'])) {
      $hours = (int)($validated['cutoff_hours'] ?? 0);
      $minutes = (int)($validated['cutoff_minutes'] ?? 0);
      $totalCutoffMinutes = ($hours * 60) + $minutes;
    }

    // If both were blank or zero, ensure it's saved as NULL
    if ($totalCutoffMinutes === 0) {
      $totalCutoffMinutes = null;
    }

    DB::beginTransaction();
    try {
      Log::info('Starting product update process', [
        'user_id' => Auth::id(),
        'business_id' => session('active_business_id'),
        'product_id' => $id,
        'request_data' => $request->except(['item_image'])
      ]);

      $productData = [
        'product_category_id' => $validated['category'],
        'item_name' => $validated['item_name'],
        'price' => $validated['price'],
        'advance_amount' => (isset($validated['preorder']) && ($validated['preorder'] === 'Yes'))
          ? ($validated['advance_amount'] ?? 0.00)
          : 0.00,
        'cutoff_minutes' => $totalCutoffMinutes,
        'is_available' => $validated['availability'] === 'Available',
        'is_pre_order' => ($validated['preorder'] ?? 'No') === 'Yes',
        'description' => $validated['description'] ?? null,
        'updated_at' => now(),
      ];

      // Handle image upload if present
      if ($request->hasFile('item_image')) {
        $file = $request->file('item_image');

        if (!$file->isValid()) {
          throw new \Exception('Image upload is invalid: ' . $file->getErrorMessage());
        }

        $disk = Storage::disk('s3');
        $imagePath = $file->store('products', 's3');

        if (!$imagePath) {
          throw new \Exception('Failed to store image on S3. Check AWS permissions or bucket policy.');
        }
        /**
         * @var \Illuminate\Filesystem\FilesystemAdapter $disk
         */

        $finalUrl = $disk->url($imagePath);

        Log::info('S3 image update complete', [
          'product_id' => $id,
          'stored_path' => $imagePath,
          'public_url' => $finalUrl
        ]);

        $productData['image_url'] = $finalUrl;
      }

      DB::table('products')->where('product_id', $id)->update($productData);

      // Step 2: Update dietary specs
      $this->syncDietarySpecifications($id, $validated['diet'] ?? []);

      DB::commit();

      return redirect()->back()->with('success', 'Product updated successfully!');
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Failed to store product', [
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return redirect()->back()->with('error', 'Failed to save product: ' . $e->getMessage())->withInput();
    }
  }
}
