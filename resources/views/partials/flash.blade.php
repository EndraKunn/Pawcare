@if (session('success'))
  <div class="flash ok" role="status">✅ <span>{{ session('success') }}</span></div>
@endif
@if (session('error'))
  <div class="flash err" role="alert">⚠️ <span>{{ session('error') }}</span></div>
@endif
@if ($errors->any() && ! $errors->has('email') && ! $errors->has('password'))
  <div class="flash err" role="alert">⚠️ <span>Periksa kembali isian formulir. Beberapa data belum valid.</span></div>
@endif
