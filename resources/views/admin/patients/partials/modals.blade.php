<div class="modal fade" id="investResModal" tabindex="-1" role="dialog" aria-labelledby="investResModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('service-save-result') }}" method="post" onsubmit="copyResTemplateToField()">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Enter Result (<span
                            id="invest_res_service_name"></span>)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="invest_res_template" class="table-reponsive" style="border: 1px solid black;">

                    </div>
                    <input type="hidden" id="invest_res_entry_id" name="invest_res_entry_id">
                    <input type="hidden" name="invest_res_template_submited" id="invest_res_template_submited">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit"
                        onclick="return confirm('Are you sure you wish to save this result entry? It can not be edited after!')"
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
