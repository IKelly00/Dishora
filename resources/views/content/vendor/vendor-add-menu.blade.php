@extends('layouts.contentNavbarLayout')

@section('title', 'Add Item')

@section('content')
    <!-- Choices CSS & JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <style>
        .main-content-area {
            background: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 6px 20px rgba(14, 30, 37, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            margin-bottom: 2rem;
        }

        .choices__inner,
        .choices[data-type*="select-one"] .choices__inner {
            border-radius: 0.375rem !important;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .choices.is-invalid .choices__inner {
            border-color: #dc3545 !important;
        }

        .choices__list--dropdown,
        .choices__list[aria-expanded] {
            z-index: 50;
        }

        .upload-box {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dcdddd;
            border: 2px dashed #ced4da;
            border-radius: 10px;
            aspect-ratio: 4 / 3;
            transition: 0.25s ease;
            cursor: pointer;
        }

        .upload-box:hover {
            background-color: #fffde7;
            border-color: #ffc107;
        }

        .upload-box.dragover {
            border-color: #ffc107;
            /* highlight on drag */
            background: #fff8e1;
        }

        /* universal fix – hides the placeholder text on multi-select when items exist  */
        .choices[data-type*="select-multiple"].has-items .choices__inner>.choices__input,
        .choices[data-type*="select-multiple"].has-items .choices__inner>.choices__placeholder,
        .choices[data-type*="select-multiple"].has-items .choices__inner>span {
            display: none !important;
        }
    </style>

    {{-- Server-Side Toastr Script --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: 'toast-top-right',
                timeOut: '7000',
                extendedTimeOut: '2000'
            };

            @if (session('success'))
                toastr.success("{{ session('success') }}");
            @endif

            @if (session('error'))
                toastr.error("{{ session('error') }}");
            @endif

            @if ($errors->any())
                @foreach ($errors->all() as $error)
                    toastr.error("{{ $error }}");
                @endforeach
            @endif
        });
    </script>

    <div class="container py-4">
        <div class="main-content-area">
            <h4 class="mb-4">Add New Item</h4>

            <form action="{{ route('vendor.menu.store') }}" id="addItemForm" method="POST" enctype="multipart/form-data"
                novalidate>
                @csrf

                <div class="row g-4">
                    <!-- ================= Left Column: Image Upload ================= -->
                    <div class="col-lg-5">
                        <div class="border rounded p-3 w-100">
                            <label class="form-label fw-bold">Item Image <span class="text-danger">*</span></label>

                            <div class="upload-box mb-3" id="uploadBox">
                                <input type="file" name="item_image" id="item_image_input" class="d-none"
                                    accept="image/*">

                                <div id="upload_placeholder" class="text-center text-muted small">
                                    <i class="ri-upload-cloud-2-line" style="font-size: 2rem;"></i><br>
                                    <span>Click or drag image here to upload</span><br>
                                    <small>(JPG, PNG)</small>
                                </div>

                                <div id="item_image_preview" class="d-none position-absolute w-100 h-100 top-0 start-0">
                                    <img src="" alt="Preview" class="w-100 h-100" style="object-fit: contain;">
                                </div>
                            </div>

                            <div class="input-group input-group-sm mb-3">
                                <span class="input-group-text">File</span>
                                <input type="text" id="item_file_name" class="form-control" value="None" readonly>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted"><i class="ri-information-line"></i> Use clear, high-quality
                                    images.</small>
                                <div>
                                    <button type="button" class="btn btn-sm btn-secondary cancel-btn">Remove</button>
                                    <button type="button" class="btn btn-sm btn-warning import-btn">Upload</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ================= Right Column: Input Fields ================= -->
                    <div class="col-lg-7">
                        <!-- SECTION: Basic Details -->
                        <div class="border-bottom mb-3 pb-2">
                            <h6 class="fw-bold text-muted mb-2">Basic Information</h6>
                            <div class="mb-3">
                                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                                <input type="text" name="item_name" id="itemName" class="form-control"
                                    placeholder="e.g. Pancit Guisado" value="{{ old('item_name') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select name="category" id="itemCategory" class="form-select" required>
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->product_category_id }}"
                                            {{ old('category') == $category->product_category_id ? 'selected' : '' }}>
                                            {{ $category->category_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
                                <input type="number" name="price" id="itemPrice" class="form-control"
                                    placeholder="145.00" step="0.01" min="1" value="{{ old('price') }}" required>
                            </div>
                        </div>

                        <!-- SECTION: Options -->
                        <div class="border-bottom mb-3 pb-2">
                            <h6 class="fw-bold text-muted mb-2">Item Options</h6>
                            <div class="mb-3">
                                <label class="form-label">Dietary Specification <span class="text-danger">*</span></label>
                                <select id="dietSelect" name="diet[]" multiple>
                                    @foreach ($dietarySpecs as $spec)
                                        <option value="{{ $spec->dietary_specification_id }}"
                                            {{ in_array($spec->dietary_specification_id, old('diet', [])) ? 'selected' : '' }}>
                                            {{ $spec->dietary_spec_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Pre-order Available? <span class="text-danger">*</span></label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" id="preorderYes" name="preorder" value="Yes"
                                            class="form-check-input"
                                            {{ old('preorder', 'Yes') == 'Yes' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="preorderYes">Yes</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" id="preorderNo" name="preorder" value="No"
                                            class="form-check-input" {{ old('preorder') == 'No' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="preorderNo">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION: Preorder Settings -->
                        <div class="border-bottom mb-3 pb-2">
                            <h6 class="fw-bold text-muted mb-2">Pre-order Settings</h6>
                            <input type="hidden" name="availability" id="itemAvailability" value="Available">

                            <div id="advanceWrapper" class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label">Advance Amount</label>

                                    <div class="d-flex align-items-center gap-2 text-muted" style="font-size: 0.8rem;">
                                        <i class="ri-information-line"></i><span>Online payment is required for
                                            pre-orders.</span>
                                    </div>
                                </div>
                                <input type="number" name="advance_amount" id="advanceAmount" class="form-control"
                                    placeholder="0.00" step="0.01" min="0"
                                    value="{{ old('advance_amount', number_format($product->advance_amount ?? 0, 2, '.', '')) }}">
                                <small class="text-muted">Only applies when pre-order is enabled.</small>
                            </div>

                            <h6 class="fw-bold text-muted mb-2">Same-Day Order Settings</h6>
                            <div id="cutoffWrapper" class="mb-3">
                                <label class="form-label">Same-Day Order Cutoff</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="input-group">
                                            <input type="number" name="cutoff_hours" id="cutoffHoursInput"
                                                class="form-control" placeholder="Hours" min="0" max="8"
                                                value="{{ old('cutoff_hours', $cutoffHours) }}">
                                            <span class="input-group-text">hr</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group">
                                            <input type="number" name="cutoff_minutes" id="cutoffMinutesInput"
                                                class="form-control" placeholder="Minutes" min="0" max="45"
                                                step="15" value="{{ old('cutoff_minutes', $cutoffMinutes) }}">
                                            <span class="input-group-text">min</span>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted">Set time buffer before closing (in 15-minute increments).</small>
                            </div>
                        </div>

                        <!-- SECTION: Description -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-muted mb-2">Description</h6>
                            <textarea name="description" id="itemDescription" rows="3" class="form-control"
                                placeholder="Briefly describe the item..." required>{{ old('description') }}</textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('vendor.menu') }}" class="btn btn-outline-secondary">Discard</a>
                            <button type="submit" class="btn btn-warning">Save Item</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Page Interactivity & Validation Script --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dietSelectElement = document.getElementById('dietSelect');

            if (dietSelectElement) {
                const dietChoices = new Choices(dietSelectElement, {
                    removeItemButton: true,
                    searchEnabled: true,
                    placeholder: true,
                    placeholderValue: 'Select Dietary Options',
                    searchPlaceholderValue: 'Search...',
                });

                const choicesWrapper = dietSelectElement.closest('.choices');

                // Hide Choices placeholder input text dynamically
                const hideChoicesPlaceholder = () => {
                    const input = choicesWrapper.querySelector('.choices__input--cloned');
                    const hasSelection = dietChoices.getValue(true).length > 0;
                    if (input) {
                        if (hasSelection) {
                            input.removeAttribute('placeholder');
                        } else {
                            input.setAttribute('placeholder', 'Select Dietary Options');
                        }
                    }
                };

                // Run whenever items change
                dietSelectElement.addEventListener('addItem', hideChoicesPlaceholder);
                dietSelectElement.addEventListener('removeItem', hideChoicesPlaceholder);

                // Run once on load to handle preselected values
                setTimeout(hideChoicesPlaceholder, 100);
            }

            // --- 2. LOGIC FOR DISABLING CUTOFF INPUTS ---
            const preorderYesRadio = document.getElementById('preorderYes');
            const preorderNoRadio = document.getElementById('preorderNo');
            const cutoffHoursInput = document.getElementById('cutoffHoursInput');
            const cutoffMinutesInput = document.getElementById('cutoffMinutesInput');
            const cutoffWrapper = document.getElementById('cutoffWrapper');
            const advanceAmount = document.getElementById('advanceAmount');
            const advanceWrapper = document.getElementById('advanceWrapper');

            function toggleCutoffState() {
                // use a clear variable name
                const isPreorder = preorderYesRadio.checked;

                // cutoff inputs: disabled when product IS a preorder (same as your intended logic)
                cutoffHoursInput.disabled = isPreorder;
                cutoffMinutesInput.disabled = isPreorder;
                if (isPreorder) {
                    cutoffHoursInput.value = '';
                    cutoffMinutesInput.value = '';
                    cutoffWrapper.style.opacity = '0.6';
                    cutoffWrapper.style.pointerEvents = 'none';
                } else {
                    cutoffWrapper.style.opacity = '1';
                    cutoffWrapper.style.pointerEvents = 'auto';
                }

                // Advance amount: enabled ONLY when Pre-order = Yes
                if (advanceAmount) {
                    advanceAmount.disabled = !isPreorder;

                    if (!isPreorder) {
                        // show 0.00 when not a preorder
                        // use toFixed to keep 2 decimal places
                        advanceAmount.value = (0).toFixed(2);
                        advanceWrapper.style.opacity = '0.6';
                        advanceWrapper.style.pointerEvents = 'none';
                    } else {
                        // restore opacity and allow interaction; do NOT clobber the value so existing saved value remains
                        advanceWrapper.style.opacity = '1';
                        advanceWrapper.style.pointerEvents = 'auto';
                    }
                }
            }

            // wire events and run once on load
            preorderYesRadio.addEventListener('change', toggleCutoffState);
            preorderNoRadio.addEventListener('change', toggleCutoffState);
            toggleCutoffState();

            // --- 3. CLIENT-SIDE FORM VALIDATION ---
            const form = document.getElementById('addItemForm');
            if (form) {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove(
                        'is-invalid'));
                    let isValid = true;
                    const handleError = (element, message) => {
                        isValid = false;
                        toastr.error(message);
                        const targetElement = element.closest('.choices') || element;
                        targetElement.classList.add('is-invalid');
                    };

                    const fieldsToValidate = [{
                            id: 'itemName',
                            name: 'Item Name'
                        },
                        {
                            id: 'itemCategory',
                            name: 'Category'
                        },
                        {
                            id: 'itemPrice',
                            name: 'Price'
                        },
                        {
                            id: 'dietSelect',
                            name: 'Dietary Specification'
                        }, // Now validating this
                        {
                            id: 'itemDescription',
                            name: 'Description'
                        }
                    ];

                    fieldsToValidate.forEach(field => {
                        const input = document.getElementById(field.id);
                        let isFieldEmpty = false;
                        if (input) {
                            if (input.multiple) {
                                isFieldEmpty = input.selectedOptions.length === 0;
                            } else {
                                isFieldEmpty = !input.value.trim();
                            }
                            if (isFieldEmpty) {
                                handleError(input, `${field.name} is required.`);
                            }
                        }
                    });

                    if (preorderNoRadio.checked) {
                        const hours = parseInt(cutoffHoursInput.value, 10) || 0;
                        const minutes = parseInt(cutoffMinutesInput.value, 10) || 0;
                        if (hours + minutes <= 0) {
                            isValid = false;
                            toastr.error('Same-Day Cutoff is required when "Pre-order" is No.');
                            cutoffHoursInput.classList.add('is-invalid');
                            cutoffMinutesInput.classList.add('is-invalid');
                        }
                    }

                    if (isValid) {
                        form.submit();
                    }
                });

                document.querySelectorAll('input, select, textarea').forEach(el => {
                    el.addEventListener('input', () => {
                        const targetElement = el.closest('.choices') || el;
                        targetElement.classList.remove('is-invalid');
                        if (el.id === 'cutoffHoursInput' || el.id === 'cutoffMinutesInput') {
                            cutoffHoursInput.classList.remove('is-invalid');
                            cutoffMinutesInput.classList.remove('is-invalid');
                        }
                    });
                });
            }


            //Drag and drop image
            const dropZone = document.getElementById('uploadBox');
            const fileInput = document.getElementById('item_image_input');

            const importBtn = document.querySelector('.import-btn');
            if (importBtn) {
                importBtn.addEventListener('click', () => fileInput.click());
            }


            const previewContainer = document.getElementById('item_image_preview');
            const previewImage = previewContainer.querySelector('img');
            const placeholder = document.getElementById('upload_placeholder');
            const fileNameDisplay = document.getElementById('item_file_name');

            // Existing click-to-upload behavior
            dropZone.addEventListener('click', () => fileInput.click());

            // Common image display method
            function showPreview(file) {
                const reader = new FileReader();
                reader.onload = e => {
                    previewImage.src = e.target.result;
                    previewContainer.classList.remove('d-none');
                    placeholder.classList.add('d-none');
                    fileNameDisplay.value = file.name;
                };
                reader.readAsDataURL(file);
            }

            // Handle file input change
            fileInput.addEventListener('change', e => {
                const file = e.target.files[0];

                // Check if the file's MIME type starts with "image/"
                if (file && file.type.startsWith('image/')) {
                    // This is a valid file, show the preview
                    showPreview(file);
                } else if (file) {
                    // This is an invalid file type
                    toastr.error('Invalid file type. Please upload an image.'); // General error

                    // Clear the invalid file from the input
                    fileInput.value = '';

                    // Trigger the 'Clear' button's click event to reset the preview UI
                    document.querySelector('.cancel-btn').click();
                }
                // If no file, do nothing
            });

            // --- Drag-and-drop events ---
            dropZone.addEventListener('dragover', e => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', e => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', e => {
                e.preventDefault();
                dropZone.classList.remove('dragover');

                const file = e.dataTransfer.files[0];

                // Check if the file's MIME type starts with "image/"
                if (file && file.type.startsWith('image/')) {
                    fileInput.files = e.dataTransfer.files; // Assign file to the input
                    showPreview(file);
                } else if (file) {
                    // File is present but wrong type
                    toastr.error('Invalid file type. Please upload an image.'); // General error
                } else {
                    // No file found in drop event
                    toastr.error('Please drop a valid image file.');
                }
            });

            // Optional: Clear button
            document.querySelector('.cancel-btn').addEventListener('click', () => {
                fileInput.value = '';
                fileNameDisplay.value = 'None';
                previewContainer.classList.add('d-none');
                placeholder.classList.remove('d-none');
            });
        });
    </script>
@endsection
