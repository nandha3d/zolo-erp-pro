<details class="ops-reverse"><summary>Reverse {{ $reverseLabel ?? 'operation' }}</summary>
@include('backend.operations.form_start',['formId'=>$reverseForm,'action'=>$reverseAction])<div class="ops-fields">@include('backend.operations.date')<label>Reason<input name="reason" required maxlength="500"></label></div><button type="submit">Post reversal</button></form></details>
