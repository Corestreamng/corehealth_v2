<div class="modal fade" id="hospital-contacts-modal" tabindex="-1" role="dialog" aria-labelledby="hospitalContactsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ appsettings('hos_color', '#007bff') }}; color: white;">
                <h5 class="modal-title" id="hospitalContactsModalLabel">
                    <i class="mdi mdi-contacts"></i> Hospital Contacts
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @php
                    $roles = class_exists('\Spatie\Permission\Models\Role') ? \Spatie\Permission\Models\Role::orderBy('name')->get() : collect();
                    $departments = class_exists('\App\Models\Department') ? \App\Models\Department::orderBy('name')->get() : collect();
                @endphp
                <!-- Action Buttons -->
                <div class="d-flex justify-content-between mb-3">
                    <div class="d-flex gap-2">
                        <select class="form-select form-control-sm" id="filter_hc_mine" style="width: auto;">
                            <option value="">All Contacts</option>
                            <option value="1">Created by Me</option>
                        </select>
                        <select class="form-select form-control-sm" id="filter_hc_role" style="width: auto;">
                            <option value="">Filter by Creator Role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                            @endforeach
                        </select>
                        <select class="form-select form-control-sm" id="filter_hc_department" style="width: auto;">
                            <option value="">Filter by Creator Department</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary" id="btn-add-contact">
                        <i class="mdi mdi-plus"></i> Add New Contact
                    </button>
                </div>

                <!-- Add/Edit Form (Hidden by default) -->
                <div id="contact-form-container" style="display: none;" class="mb-4 p-3 border rounded bg-light">
                    <h6 id="contact-form-title">Add Contact</h6>
                    <form id="hospital-contact-form">
                        @csrf
                        <input type="hidden" id="contact_id" name="contact_id">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="contact_name" name="name" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Phone</label>
                                <input type="text" class="form-control" id="contact_phone" name="phone">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12 form-group">
                                <label>Description</label>
                                <textarea class="form-control" id="contact_description" name="description" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="mt-3 text-right">
                            <button type="button" class="btn btn-secondary" id="btn-cancel-contact">Cancel</button>
                            <button type="submit" class="btn btn-success" id="btn-save-contact">Save Contact</button>
                        </div>
                    </form>
                </div>

                <!-- Contacts Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped w-100" id="hospital-contacts-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Description</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let contactsTable;
    
    $('#hospital-contacts-modal').on('shown.bs.modal', function () {
        if (!contactsTable) {
            contactsTable = $('#hospital-contacts-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("hospital-contacts.index") }}',
                    data: function(d) {
                        d.filter_mine = $('#filter_hc_mine').val();
                        d.filter_role = $('#filter_hc_role').val();
                        d.filter_department = $('#filter_hc_department').val();
                    }
                },
                columns: [
                    { data: 'name', name: 'name' },
                    { data: 'phone', name: 'phone' },
                    { data: 'description', name: 'description' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ]
            });

            $('#filter_hc_mine, #filter_hc_role, #filter_hc_department').on('change', function() {
                contactsTable.ajax.reload();
            });
        } else {
            contactsTable.ajax.reload(null, false);
        }
    });

    $('#btn-add-contact').click(function() {
        $('#hospital-contact-form')[0].reset();
        $('#contact_id').val('');
        $('#contact-form-title').text('Add Contact');
        $('#contact-form-container').slideDown();
    });

    $('#btn-cancel-contact').click(function() {
        $('#contact-form-container').slideUp();
        $('#hospital-contact-form')[0].reset();
    });

    $('#hospital-contact-form').on('submit', function(e) {
        e.preventDefault();
        
        let id = $('#contact_id').val();
        let url = id ? '/hospital-contacts/' + id : '/hospital-contacts';
        let method = id ? 'PUT' : 'POST';
        
        $.ajax({
            url: url,
            type: method,
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', response.message, 'success');
                    $('#contact-form-container').slideUp();
                    contactsTable.ajax.reload(null, false);
                }
            },
            error: function(xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Something went wrong', 'error');
            }
        });
    });

    $(document).on('click', '.edit-contact', function() {
        let id = $(this).data('id');
        $.get('/hospital-contacts/' + id, function(response) {
            if (response.success) {
                $('#contact_id').val(response.data.id);
                $('#contact_name').val(response.data.name);
                $('#contact_phone').val(response.data.phone);
                $('#contact_description').val(response.data.description);
                $('#contact-form-title').text('Edit Contact');
                $('#contact-form-container').slideDown();
            }
        });
    });

    $(document).on('click', '.delete-contact', function() {
        let id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/hospital-contacts/' + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success');
                            contactsTable.ajax.reload(null, false);
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.message || 'Failed to delete', 'error');
                    }
                });
            }
        });
    });
});
</script>
