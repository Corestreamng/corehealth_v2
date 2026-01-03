<div class="modal fade" id="investResModal" tabindex="-1" role="dialog" aria-labelledby="investResModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('service-save-result') }}" method="post" enctype="multipart/form-data" onsubmit="copyResTemplateToField()">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Enter Result (<span
                            id="invest_res_service_name"></span>)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
                </div>
                <div class="modal-body">
                    <textarea id="invest_res_template_editor" class="ckeditor"></textarea>
                    <input type="hidden" id="invest_res_entry_id" name="invest_res_entry_id">
                    <input type="hidden" name="invest_res_template_submited" id="invest_res_template_submited">
                    <input type="hidden" id="invest_res_is_edit" name="invest_res_is_edit" value="0">
                    <input type="hidden" id="deleted_attachments" name="deleted_attachments" value="[]">

                    <hr>

                    <!-- Existing Attachments -->
                    <div id="existing_attachments_container" style="display: none;">
                        <label><i class="mdi mdi-paperclip"></i> Existing Attachments</label>
                        <div id="existing_attachments_list" class="mb-3"></div>
                    </div>

                    <!-- New File Upload -->
                    <div class="form-group">
                        <label for="result_attachments"><i class="mdi mdi-file-plus"></i> Add New Files (Optional)</label>
                        <input type="file" class="form-control" id="result_attachments" name="result_attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">You can attach multiple files (PDF, Images, Word documents). Max 10MB per file.</small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" id="invest_res_submit_btn"
                        class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="investResViewModal" tabindex="-1" role="dialog" aria-labelledby="investResViewModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="investResViewModalLabel">View Result (<span
                        class="invest_res_service_name_view"></span>)</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                </button>
            </div>
            <div class="modal-body">
                @php
                    $sett = appsettings();
                @endphp
                <div id="resultViewTable">
                    <table class="table table-bordered">
                        <tr>
                            <td style="max-width: 20%">
                                <img src="data:image/jpeg;base64,{{ $sett->logo ?? '' }}" alt="Image"
                                    style="width: 100px" />

                            </td>
                            <td colspan="3">
                                {{ $sett->site_name }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">{{ $sett->contact_address }}</td>
                            <td>{{ $sett->contact_phones }}</td>
                            <td>{{ $sett->contact_emails }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" id="invest_name">

                            </td>
                            <td>
                                <span class="invest_res_service_name_view"></span>
                            </td>
                            <td>
                                Sample Date :<span id="res_sample_date"></span>
                                <br>
                                Result Date: <span id="res_result_date"></span>
                                <br>
                                Result By: <span id="res_result_by"></span>
                            </td>
                        </tr>
                    </table>
                    <p id="invest_res">

                    </p>
                    <div id="invest_attachments" style="margin-top: 15px;">
                        <!-- Attachments will be inserted here by JavaScript -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                {{-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> --}}
                <button type="submit" onclick="PrintElem('resultViewTable')" class="btn btn-primary">Print</button>
            </div>
            </form>
        </div>
    </div>
</div>

{{-- Imaging Result Entry Modal --}}
<div class="modal fade" id="imagingResModal" tabindex="-1" role="dialog" aria-labelledby="imagingResModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('save-imaging-result') }}" method="post" enctype="multipart/form-data" onsubmit="copyImagingResTemplateToField()">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="imagingResModalLabel">Enter Imaging Result (<span
                            id="imaging_res_service_name"></span>)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
                </div>
                <div class="modal-body">
                    <textarea id="imaging_res_template_editor" class="ckeditor"></textarea>
                    <input type="hidden" id="imaging_res_entry_id" name="imaging_res_entry_id">
                    <input type="hidden" name="imaging_res_template_submited" id="imaging_res_template_submited">
                    <input type="hidden" id="imaging_res_is_edit" name="imaging_res_is_edit" value="0">
                    <input type="hidden" id="imaging_deleted_attachments" name="deleted_attachments" value="[]">

                    <hr>

                    <!-- Existing Attachments -->
                    <div id="imaging_existing_attachments_container" style="display: none;">
                        <label><i class="mdi mdi-paperclip"></i> Existing Attachments</label>
                        <div id="imaging_existing_attachments_list" class="mb-3"></div>
                    </div>

                    <!-- New File Upload -->
                    <div class="form-group">
                        <label for="result_attachments"><i class="mdi mdi-file-plus"></i> Add New Files (Optional)</label>
                        <input type="file" class="form-control" id="imaging_result_attachments" name="result_attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">You can attach multiple files (PDF, Images, Word documents). Max 10MB per file.</small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" id="imaging_res_submit_btn"
                        class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Imaging Result View Modal --}}
<div class="modal fade" id="imagingResViewModal" tabindex="-1" role="dialog" aria-labelledby="imagingResViewModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="imagingResViewModalLabel">View Imaging Result (<span
                        class="imaging_res_service_name_view"></span>)</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                </button>
            </div>
            <div class="modal-body">
                @php
                    $sett = appsettings();
                @endphp
                <div id="imagingResultViewTable">
                    <table class="table table-bordered">
                        <tr>
                            <td style="max-width: 20%">
                                <img src="data:image/jpeg;base64,{{ $sett->logo ?? '' }}" alt="Image"
                                    style="width: 100px" />

                            </td>
                            <td colspan="3">
                                {{ $sett->site_name }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">{{ $sett->contact_address }}</td>
                            <td>{{ $sett->contact_phones }}</td>
                            <td>{{ $sett->contact_emails }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" id="imaging_name">

                            </td>
                            <td>
                                <span class="imaging_res_service_name_view"></span>
                            </td>
                            <td>
                                Result Date: <span id="imaging_result_date"></span>
                                <br>
                                Result By: <span id="imaging_result_by"></span>
                            </td>
                        </tr>
                    </table>
                    <p id="imaging_res">

                    </p>
                    <div id="imaging_attachments" style="margin-top: 15px;">
                        <!-- Attachments will be inserted here by JavaScript -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" onclick="PrintElem('imagingResultViewTable')" class="btn btn-primary">Print</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="nursingNoteModal" tabindex="-1" role="dialog" aria-labelledby="nursingNoteModal"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="investResModalLabel">Nursing Note Result (<span
                        id="note_type_name_"></span>)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                </button>
            </div>
            <div class="modal-body">
                <div id="nursing_note_template_" class="table-reponsive" style="border: 1px solid black;">

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="assignBillModal" tabindex="-1" role="dialog" aria-labelledby="assignBillModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('assign-bill') }}" method="post">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign Bill </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="assign_bed_req_id_" name="assign_bed_req_id">
                    <div class="form-group">
                        <label for="admit_days">No of days admitted</label>
                        <input type="text" name="days" class="form-control" id="admit_days" readonly>
                    </div>
                    <div class="form-group">
                        <h6>Bed Details</h6>
                        <p id="admit_bed_details"></p>
                        <label>Price</label>
                        <input type="text" class="form-control" id="admit_price" readonly>
                    </div>
                    <div class="form-group">
                        <label for="">Total</label>
                        <input type="text" id="admit_total" class="form-control" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit"
                        onclick="return confirm('Are you sure you wish to save this entry? It can not be edited after!')"
                        class="btn btn-primary">Save Bill </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="assignBedModal" tabindex="-1" role="dialog" aria-labelledby="assignBedModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('assign-bed') }}" method="post">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign Bed </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="assign_bed_req_id" name="assign_bed_req_id">
                    {{-- Redundent --}}
                    <input type="hidden" id="assign_bed_reassign" name="assign_bed_reassign">
                    <div class="form-group">
                        <label for="">Select Bed</label>
                        <select name="bed_id" class="form-control">
                            <option value="">--select bed--</option>
                            @foreach ($avail_beds as $bed)
                                <option value="{{ $bed->id }}">{{ $bed->name }}[Price: NGN
                                    {{ $bed->price }}, Ward: {{ $bed->ward }}, Unit: {{ $bed->unit }}]
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit"
                        onclick="return confirm('Are you sure you wish to save this entry? It can not be edited after!')"
                        class="btn btn-primary">Assign Bed </button>
                </div>
            </form>
        </div>
    </div>
</div>
