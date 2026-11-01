@extends('layouts.public')
@section('title', 'Beranda')

@section('bare')
<div class="hero">
  <div>
    <h1>Penitipan &amp; Perawatan Terbaik untuk Sahabat Anda</h1>
    <p>Booking layanan online, pembayaran terverifikasi, dan pantau status hewanmu kapan saja — dari banyak penyedia layanan terpercaya dalam satu platform.</p>
    <div class="cta">
      <a class="btn lg" href="{{ route('catalog') }}">Booking Sekarang</a>
      <a class="btn ghost lg" href="{{ route('register.owner') }}">Daftar sebagai Mitra Bisnis</a>
    </div>
    <div class="chips"><span class="chip">🏨 Penitipan Hewan</span><span class="chip">✂️ Grooming</span><span class="chip">🩺 Perawatan</span><span class="chip">📊 Laporan Bisnis</span></div>
  </div>
<figure class="hero-photo">
    <img
        src="{{ asset('images/pawcare/hero/hero.png') }}"
        alt="Anjing, kucing, kelinci, marmut, dan hamster"
        loading="eager"
        fetchpriority="high"
    >
</figure>
</div>
<div class="pad">
  @include('partials.flash')
  <div class="sec-t">Layanan Unggulan</div>
  <div class="grid g3">
    @foreach ([
      ['penitipan', 'Penitipan', 'Penitipan Hewan', 'Kandang bersih dan jadwal makan teratur saat Anda bepergian.'],
      ['grooming', 'Grooming', 'Grooming', 'Mandi, potong bulu, dan potong kuku oleh groomer berpengalaman.'],
      ['perawatan', 'Perawatan', 'Perawatan Hewan', 'Cek kondisi dasar, pemberian vitamin, dan perawatan rutin.'],
    ] as [$slug, $cat, $title, $desc])
      <div class="card media">
        <a class="ph" href="{{ route('catalog', ['category' => $cat]) }}" tabindex="-1" aria-hidden="true">
          <x-photo :src="pawcare_img('services/'.$slug, 'service-'.$slug.'.svg')" :fallback="pawcare_fallback('service-'.$slug.'.svg')" :alt="$title" />
        </a>
        <div class="bd">
          <h3>{{ $title }}</h3>
          <p class="sub">{{ $desc }}</p>
          <a class="btn sm line" href="{{ route('catalog', ['category' => $cat]) }}">Lihat bisnis {{ strtolower($cat) }}</a>
        </div>
      </div>
    @endforeach
  </div>


<style>
  .paw-steps {
    align-items: stretch;
  }

  .paw-step {
    padding: 16px;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 14px;
    transition: transform .2s ease, border-color .2s ease;
  }

  .paw-step:hover {
    transform: translateY(-4px);
    border-color: rgba(147, 197, 253, .48);
  }

  .paw-step-art {
    height: 132px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    overflow: hidden;
  }

  .paw-step-art svg {
    width: 100%;
    height: 124px;
    max-width: 210px;
    display: block;
  }

  .paw-art-blue { background: #dbeafe; }
  .paw-art-green { background: #dcfce7; }
  .paw-art-orange { background: #ffedd5; }
  .paw-art-purple { background: #ede9fe; }

  .paw-step-info {
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }

  .paw-step-number {
    flex: 0 0 38px;
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: rgba(148, 163, 184, .2);
    font-size: 20px;
    font-weight: 800;
  }

  .paw-step-info h3 {
    margin: 2px 0 6px;
    font-size: 16px;
  }

  .paw-step-info p {
    margin: 0;
    line-height: 1.65;
  }

  @media (prefers-reduced-motion: reduce) {
    .paw-step {
      transition: none;
    }

    .paw-step:hover {
      transform: none;
    }
  }
</style>

<div class="sec-t mt2">Cara Kerja</div>

<div class="grid g4 paw-steps">

  {{-- Langkah 1 --}}
  <article class="card paw-step">
    <div class="paw-step-art paw-art-blue">
      <svg viewBox="0 0 200 125" role="img"
           aria-label="Ilustrasi anjing dan kucing sebagai profil hewan">
        <circle cx="100" cy="61" r="48" fill="#bfdbfe"/>
        <circle cx="65" cy="34" r="8" fill="#93c5fd"/>
        <circle cx="145" cy="88" r="6" fill="#93c5fd"/>
        <g>
          <path d="M49 46 L45 26 Q44 20 53 25 L69 40"
                fill="#fb923c" stroke="#9a3412" stroke-width="2"/>
          <path d="M81 46 L88 27 Q90 21 96 29 L91 48"
                fill="#fb923c" stroke="#9a3412" stroke-width="2"/>
          <ellipse cx="73" cy="62" rx="27" ry="29" fill="#fdba74"/>
          <ellipse cx="73" cy="71" rx="17" ry="13" fill="#ffedd5"/>
          <circle cx="64" cy="59" r="2.7" fill="#422006"/>
          <circle cx="82" cy="59" r="2.7" fill="#422006"/>
          <ellipse cx="73" cy="69" rx="4" ry="3" fill="#7c2d12"/>
          <path d="M69 75 Q73 80 77 75"
                fill="none" stroke="#7c2d12" stroke-width="2"
                stroke-linecap="round"/>
        </g>
        <g>
          <path d="M109 41 L109 19 L127 34 Z"
                fill="#64748b" stroke="#334155" stroke-width="2"/>
          <path d="M139 34 L157 19 L156 43"
                fill="#64748b" stroke="#334155" stroke-width="2"/>
          <ellipse cx="133" cy="55" rx="26" ry="25" fill="#94a3b8"/>
          <path d="M120 63 Q133 77 146 63"
                fill="#f8fafc"/>
          <circle cx="124" cy="53" r="2.7" fill="#0f172a"/>
          <circle cx="142" cy="53" r="2.7" fill="#0f172a"/>
          <path d="M130 62 L136 62 L133 66 Z" fill="#ec4899"/>
          <path d="M133 66 V70"
                stroke="#334155" stroke-width="1.5"/>
        </g>
        <path d="M94 99 Q100 89 106 99 Q117 106 108 112
                 Q100 116 94 109 Q88 116 80 112 Q71 106 82 99
                 Q88 89 94 99Z" fill="#60a5fa"/>
      </svg>
    </div>

    <div class="paw-step-info">
      <span class="paw-step-number">1</span>
      <div>
        <h3>Daftar &amp; tambah hewan</h3>
        <p class="sub">Isi data hewan sekali, dipakai di setiap booking.</p>
      </div>
    </div>
  </article>

  {{-- Langkah 2 --}}
  <article class="card paw-step">
    <div class="paw-step-art paw-art-green">
      <svg viewBox="0 0 200 125" role="img"
           aria-label="Ilustrasi tempat perawatan hewan">
        <circle cx="100" cy="62" r="48" fill="#bbf7d0"/>
        <rect x="35" y="48" width="130" height="59"
              rx="5" fill="#fff" stroke="#15803d" stroke-width="2"/>
        <path d="M27 49 L48 24 H153 L174 49 Z"
              fill="#16a34a"/>
        <path d="M48 49 V33 H71 V49 M80 49 V33 H103 V49
                 M112 49 V33 H135 V49 M144 49 V33 H153 V49"
              fill="#dcfce7"/>
        <rect x="45" y="61" width="29" height="29"
              rx="3" fill="#dbeafe" stroke="#60a5fa" stroke-width="2"/>
        <path d="M59 61 V90 M45 75 H74"
              stroke="#60a5fa" stroke-width="2"/>
        <rect x="119" y="66" width="31" height="41"
              rx="3" fill="#bbf7d0" stroke="#15803d" stroke-width="2"/>
        <circle cx="141" cy="87" r="1.8" fill="#15803d"/>
        <g transform="translate(78 59)">
          <path d="M4 14 L0 1 Q0 -3 8 5 L14 9
                   M39 14 L43 1 Q43 -3 35 5 L29 9"
                fill="#fbbf24" stroke="#92400e" stroke-width="1.5"/>
          <ellipse cx="22" cy="21" rx="21" ry="19"
                   fill="#fbbf24" stroke="#92400e" stroke-width="1.5"/>
          <ellipse cx="22" cy="27" rx="12" ry="9" fill="#fef3c7"/>
          <circle cx="15" cy="19" r="2" fill="#422006"/>
          <circle cx="29" cy="19" r="2" fill="#422006"/>
          <ellipse cx="22" cy="25" rx="3" ry="2" fill="#92400e"/>
        </g>
        <path d="M36 108 H167" stroke="#15803d"
              stroke-width="3" stroke-linecap="round"/>
      </svg>
    </div>

    <div class="paw-step-info">
      <span class="paw-step-number">2</span>
      <div>
        <h3>Pilih bisnis &amp; layanan</h3>
        <p class="sub">Bandingkan mitra, lihat harga, tentukan jadwal.</p>
      </div>
    </div>
  </article>

  {{-- Langkah 3 --}}
  <article class="card paw-step">
    <div class="paw-step-art paw-art-orange">
      <svg viewBox="0 0 200 125" role="img"
           aria-label="Ilustrasi kalender booking dan pembayaran">
        <circle cx="98" cy="62" r="48" fill="#fed7aa"/>
        <rect x="34" y="30" width="88" height="78"
              rx="8" fill="#fff" stroke="#c2410c" stroke-width="2"/>
        <path d="M34 46 Q34 30 42 30 H114 Q122 30 122 46 V49 H34Z"
              fill="#fb923c"/>
        <path d="M52 24 V37 M104 24 V37"
              stroke="#7c2d12" stroke-width="5"
              stroke-linecap="round"/>
        <g fill="#fdba74">
          <rect x="46" y="58" width="12" height="10" rx="2"/>
          <rect x="64" y="58" width="12" height="10" rx="2"/>
          <rect x="82" y="58" width="12" height="10" rx="2"/>
          <rect x="46" y="75" width="12" height="10" rx="2"/>
          <rect x="64" y="75" width="12" height="10" rx="2"/>
        </g>
        <rect x="77" y="72" width="44" height="29"
              rx="5" fill="#22c55e" stroke="#15803d" stroke-width="2"/>
        <path d="M88 86 L95 92 L109 78"
              fill="none" stroke="#fff" stroke-width="4"
              stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M139 44 L144 54 L155 56 L147 64
                 L149 75 L139 70 L129 75 L131 64
                 L123 56 L134 54 Z" fill="#f59e0b"/>
      </svg>
    </div>

    <div class="paw-step-info">
      <span class="paw-step-number">3</span>
      <div>
        <h3>Booking &amp; bayar</h3>
        <p class="sub">Tentukan jadwal, lakukan pembayaran, lalu unggah bukti jika diperlukan.</p>
      </div>
    </div>
  </article>

  {{-- Langkah 4 --}}
  <article class="card paw-step">
    <div class="paw-step-art paw-art-purple">
      <svg viewBox="0 0 200 125" role="img"
           aria-label="Ilustrasi hewan dan daftar pemantauan status layanan">
        <circle cx="99" cy="62" r="48" fill="#ddd6fe"/>
        <rect x="110" y="22" width="52" height="79"
              rx="6" fill="#fff" stroke="#7c3aed" stroke-width="2"/>
        <rect x="123" y="17" width="26" height="10"
              rx="4" fill="#8b5cf6"/>
        <g stroke="#a78bfa" stroke-width="2" stroke-linecap="round">
          <path d="M131 44 H151 M131 61 H151 M131 78 H151"/>
        </g>
        <g fill="#22c55e">
          <circle cx="120" cy="44" r="5"/>
          <circle cx="120" cy="61" r="5"/>
          <circle cx="120" cy="78" r="5"/>
        </g>
        <g fill="none" stroke="#fff" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round">
          <path d="M117 44 L119 46 L123 41"/>
          <path d="M117 61 L119 63 L123 58"/>
          <path d="M117 78 L119 80 L123 75"/>
        </g>
        <g transform="translate(38 42)">
          <path d="M9 17 L2 0 Q0 -5 12 4 L20 11
                   M48 17 L56 0 Q58 -5 46 4 L38 11"
                fill="#a78bfa" stroke="#5b21b6" stroke-width="2"/>
          <ellipse cx="29" cy="30" rx="29" ry="26"
                   fill="#c4b5fd" stroke="#5b21b6" stroke-width="2"/>
          <ellipse cx="29" cy="39" rx="16" ry="12" fill="#fff"/>
          <circle cx="19" cy="28" r="3" fill="#312e81"/>
          <circle cx="39" cy="28" r="3" fill="#312e81"/>
          <ellipse cx="29" cy="37" rx="4" ry="3" fill="#7c3aed"/>
          <path d="M24 43 Q29 48 34 43"
                fill="none" stroke="#5b21b6" stroke-width="2"
                stroke-linecap="round"/>
        </g>
        <circle cx="64" cy="99" r="11" fill="#facc15"/>
        <path d="M64 92 L66 97 L72 98 L67 102
                 L68 108 L64 105 L59 108 L60 102
                 L56 98 L62 97Z" fill="#fff"/>
      </svg>
    </div>

    <div class="paw-step-info">
      <span class="paw-step-number">4</span>
      <div>
        <h3>Pantau status</h3>
        <p class="sub">Lihat perkembangan layanan dan riwayat transaksi.</p>
      </div>
    </div>
  </article>

</div>


  <div class="sec-t mt2">Mitra Bisnis<small>Setiap bisnis punya layanan, harga, dan data sendiri</small></div>
  @if ($businesses->isEmpty())
    <x-empty icon="🏢" title="Belum ada mitra bisnis" />
  @else
    <div class="grid g3">@foreach ($businesses as $b) @include('partials.biz-card', ['b' => $b]) @endforeach</div>
  @endif

  <div class="card gold mt2 row sp wrapf">
    <div><h3>Punya usaha penitipan atau grooming?</h3><p class="sub" style="margin:0">Kelola layanan, booking, pembayaran, dan laporan dari satu dashboard.</p></div>
    <a class="btn" href="{{ route('register.owner') }}">Daftar sebagai Mitra Bisnis</a>
  </div>
</div>
@endsection
