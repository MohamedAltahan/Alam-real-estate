<?php

namespace Tests\Unit;

use App\Support\FullTextQuery;
use App\Support\PhoneCountries;
use App\Support\PhoneNumber;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_split_handles_all_stored_formats(): void
    {
        $this->assertSame(['code' => '+965', 'national' => '55112233'], PhoneNumber::split('+96555112233'));
        $this->assertSame(['code' => '+966', 'national' => '501234567'], PhoneNumber::split('+966 501234567'));
        $this->assertSame(['code' => '+965', 'national' => '55112233'], PhoneNumber::split('0096555112233'));
        $this->assertSame(['code' => '+965', 'national' => '55112233'], PhoneNumber::split('96555112233'));
        $this->assertSame(['code' => '+965', 'national' => '91234567'], PhoneNumber::split('91234567'));
        $this->assertSame(['code' => '+965', 'national' => '91234567'], PhoneNumber::split('091234567'));
        $this->assertSame(['code' => '+20', 'national' => '1001234567'], PhoneNumber::split('+20 1001234567'));
        $this->assertSame(['code' => '+1684', 'national' => '5551234'], PhoneNumber::split('+1684 555 1234'));
        $this->assertSame(['code' => '+1', 'national' => '2125551234'], PhoneNumber::split('+1 212 555 1234'));
        $this->assertSame(['code' => '+965', 'national' => ''], PhoneNumber::split(null));
    }

    public function test_format_and_digits(): void
    {
        $this->assertSame('+965 55112233', PhoneNumber::format('+965', '55112233'));
        $this->assertSame('96555112233', PhoneNumber::digits('+965', '55112233'));
        $this->assertSame('', PhoneNumber::format('+965', ''));
        $this->assertSame('+965 123', PhoneNumber::format(null, '123'));
    }

    public function test_masked_hides_the_last_two_digits_and_forces_ltr(): void
    {
        $this->assertSame("\u{200E}+965551122xx", PhoneNumber::masked('+965', '55112233'));
        $this->assertSame("\u{200E}+9665012345xx", PhoneNumber::masked('+966', '501234567'));
        $this->assertSame('', PhoneNumber::masked('+965', ''));
        $this->assertSame('', PhoneNumber::masked(null, null));
        $this->assertSame("\u{200E}+96599000005", PhoneNumber::ltr('+965 99 000 005'));
        $this->assertSame('', PhoneNumber::ltr(null));
    }

    public function test_country_list_has_kuwait_first_and_unique_codes_ordered_by_length(): void
    {
        $this->assertSame('KW', PhoneCountries::all()[0]['iso']);
        $this->assertSame('+965', PhoneCountries::DEFAULT);
        $this->assertTrue(PhoneCountries::isValid('+966'));
        $this->assertFalse(PhoneCountries::isValid('+999'));
        $this->assertSame('Kuwait', PhoneCountries::find('+965')['en']);
        $this->assertSame('US', PhoneCountries::find('+1')['iso']);

        $lengths = array_map('strlen', PhoneCountries::codesByLength());
        $sorted = $lengths;
        rsort($sorted);
        $this->assertSame($sorted, $lengths);
        $this->assertGreaterThan(200, count(PhoneCountries::all()));
    }

    public function test_fulltext_boolean_query_builder(): void
    {
        $this->assertSame('+شقة* +السالمية*', FullTextQuery::boolean('شقة  السالمية'));
        $this->assertSame('+villa* +sea*', FullTextQuery::boolean('+villa -sea*'));
        $this->assertSame('', FullTextQuery::boolean('a'));
    }
}
