<input type="hidden" name="from" value="{{ $from }}">
<input type="hidden" name="to" value="{{ $to }}">
<input type="hidden" name="q" value="{{ $search }}">
@if($excludeUsed)<input type="hidden" name="exclude_used" value="1">@endif
