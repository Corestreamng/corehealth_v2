@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Create a new message</h1>
        <form action="{{ route('messages.store') }}" method="post">
            {{ csrf_field() }}
            <div class="col-md-12">
                <!-- Subject Form Input -->
                <div class="form-group">
                    <label class="control-label">Subject</label>
                    <input type="text" class="form-control" name="subject" placeholder="Subject"
                        value="{{ old('subject') }}">
                </div>

                <!-- Message Form Input -->
                <div class="form-group">
                    <label class="control-label">Message</label>
                    <textarea name="message" class="form-control">{{ old('message') }}</textarea>
                </div>

                @if ($users->count() > 0)
                    <h3>Select recipients</h3><br>
                    <div class="row">
                        @foreach ($users as $user)
                            <div class="col-3">
                                <label for="user{{ $user->id }}">
                                    {{ userfullname($user->id) }}
                                </label>
                                <input type="checkbox" name="recipients[]" id="user{{ $user->id }}"
                                    value="{{ $user->id }}">
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Submit Form Input -->
                <div class="form-group">
                    <button type="submit" class="btn btn-primary form-control">Submit</button>
                </div>
            </div>
        </form>
    </div>
@stop
