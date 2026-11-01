@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'options' => [], 'placeholder' => '', 'full' => false, 'required' => false, 'rows' => 3])
@php
  $errKey = str_replace(['[', ']'], ['.', ''], $name);
  $val = $type === 'password' ? null : old($errKey, $value);
@endphp
<label class="fld" @if($full) style="grid-column:1/-1" @endif>
  <span>{{ $label }}@if($required) <b class="gold-t">*</b>@endif</span>
  @if ($type === 'select')
    <select name="{{ $name }}" @if($required) required @endif {{ $attributes }}>
      @foreach ($options as $k => $v)
        <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $v }}</option>
      @endforeach
    </select>
  @elseif ($type === 'textarea')
    <textarea name="{{ $name }}" rows="{{ $rows }}" placeholder="{{ $placeholder }}" @if($required) required @endif {{ $attributes }}>{{ $val }}</textarea>
  @else
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $val }}" placeholder="{{ $placeholder }}" @if($required) required @endif {{ $attributes }}>
  @endif
  @if ($hint)<em class="h" style="font-style:normal">{{ $hint }}</em>@endif
  @error($errKey)<em class="err-t" style="font-style:normal">{{ $message }}</em>@enderror
</label>
