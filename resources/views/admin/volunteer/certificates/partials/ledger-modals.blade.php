{{--
  نافذة «عرض» لكلّ شهادة — مرّة واحدة لكلّ صفٍّ لا مرّتين (سطح المكتب
  والموبايل يتشاركان نفس النافذة عبر معرّفها). المتغيّر المتوقَّع: $issued.
--}}
@foreach ($issued as $certificate)
    @include('certificates.partials.certificate-modal', ['certificate' => $certificate])
@endforeach
