@extends('admin.layouts.app')
@section('title', 'Hompage')
@section('page_name', 'Home')
@section('content')
<div class="row">
    <div class="col-md-6 stretch-card grid-margin">
        <table class="table table-sm table-bordered table-striped">
            <thead>
              <tr>
                <th scope="col"></th>
                <th scope="col">Name</th>
                <th scope="col">Email</th>
                <th scope="col">Phone</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input type="checkbox"></td>
                <td>John Smith</td>
                <td>john.smith@example.com</td>
                <td>555-555-5555</td>
              </tr>
            </tbody>
          </table>

</div>
@endsection
