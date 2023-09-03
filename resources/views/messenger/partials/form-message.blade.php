<h2>Add a new message</h2>
<form action="{{ route('messages.update', $thread->id) }}" method="post">
    {{ method_field('put') }}
    {{ csrf_field() }}

    <!-- Message Form Input -->
    <div class="form-group">
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
                    <input type="checkbox" name="recipients[]" id="user{{ $user->id }}" value="{{ $user->id }}">
                </div>
            @endforeach
        </div>
    @endif

    <!-- Submit Form Input -->
    <div class="form-group">
        <button type="submit" class="btn btn-primary form-control">Submit</button>
    </div>
</form>
