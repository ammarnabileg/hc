<?php

namespace App\Services\Wallet;

use RuntimeException;

/**
 * خطأ عمليّة ماليّة يُعرَض للمستخدم كما هو.
 *
 * ولذلك **كلّ نصٍّ يُرمى من هنا مكتوبٌ بقاعدة 2.17-ب: ماذا حدث + ماذا تفعل** —
 * لا «حدث خطأ» ولا رقم خطأ مجرّد.
 */
class WalletException extends RuntimeException {}
