<?php

namespace Tests\Feature;

use App\Support\NoticeTranslator;
use Tests\TestCase;

/** An old English notice reads in Arabic for an Arabic reader when the sentence is known, and is left alone when it is not. */
class NoticeTranslatorTest extends TestCase
{
    public function test_known_sentences_and_sentences_with_placeholders_are_translated(): void
    {
        $this->assertSame('إحدى منصات الإعلانات لا تستلم مبيعاتك', NoticeTranslator::toArabic('An ad platform is not receiving your sales'));
        $this->assertSame('وصلت الشحنة رقم 12 بفروق في العدد', NoticeTranslator::toArabic('Delivery #12 arrived with differences'));
        $this->assertSame('Something nobody wrote', NoticeTranslator::toArabic('Something nobody wrote'));
        $this->assertSame('مرحبا', NoticeTranslator::toArabic('مرحبا'));
    }
}
