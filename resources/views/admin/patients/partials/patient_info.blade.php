<div class="row">
    <div class="col-md-9">
        <h1>{{ userfullname($user->id) }}</h1>
        <h3>File No: {{ $patient->file_no }}</h3>
        @if ($user->old_records)
            <div class="form-group">
                <a href="{!! url('storage/image/user/old_records/' . $user->old_records) !!}" target="_blank"><i class="fa fa-file"></i> Old Records</a>
                <br>
            </div>
        @else
            <div class="form-group">
                <a href="#"><i class="fa fa-file"></i> No Old Records Attached</a>
                <br>
            </div>
        @endif
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <img src="{!! url('storage/image/user/' . $user->filename) !!}" valign="middle" width="150px" height="120px" />
            <br>
        </div>
    </div>
</div>
<br>
<hr>
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped">
        <tbody>
            <tr>
                <th>Gender: </th>
                <td>{{ $patient->gender ?? 'N/A' }}</td>
                <th>D.O.B:</th>
                <td>{{ $patient->dob ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Blood Group:</th>
                <td>{{ $patient->blood_group ?? 'N/A' }}</td>
                <th>Genotype :</th>
                <td>{{ $patient->genotype ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Nationality: </th>
                <td>{{ $patient->nationality ?? 'N/A' }}</td>
                <th>Ethnicity:</th>
                <td>{{ $patient->ethnicity ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Address: </th>
                <td>{{ $patient->address ?? 'N/A' }}</td>
                <th>Other info:</th>
                <td>{{ $patient->misc ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Insurance/HMO: </th>
                <td>{{ $patient->hmo->name ?? 'N/A' }}</td>
                <th>HMO No:</th>
                <td>{{ $patient->hmo_no ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Disability status:</th>
                <td>{{ $patient->disability == 1 ? 'Disabled' : 'None' }}</td>
                <th>Phone: </th>
                <td>{{ $patient->phone_no }}</td>
            </tr>
            <tr>
                <th>Next Of Kin: </th>
                <td>{{ $patient->next_of_kin_name ?? 'N/A' }}</td>
                <th>Other next of kin info:</th>
                <td>
                    Phone : {{ $patient->next_of_kin_phone ?? 'N/A' }} <br>
                    Address : {{ $patient->next_of_kin_address ?? 'N/A' }}
                </td>
            </tr>
        </tbody>
    </table>
</div>
@if ($user->assignRole == 1)

    <div class="form-group">
        <label for="inputPassword3" class="col-md-2 control-label">Roles Assigned:</label>

        <div class="col-md-10">
            @if (!empty($user->getRoleNames()))
                @foreach ($user->getRoleNames() as $v)
                    <label class="badge badge-success">{{ $v }}</label>
                @endforeach
            @endif
        </div>
    </div>

@endif

@if ($user->assignPermission == 1)

    <div class="form-group">
        <label for="inputPassword3" class="col-md-2 control-label">Permission Assigned:</label>

        <div class="col-md-10">
            @if (!empty($user->getPermissionNames()))
                @foreach ($user->getPermissionNames() as $v)
                    <label class="badge badge-success">{{ $v }}</label>
                @endforeach
            @endif
        </div>
    </div>

@endif