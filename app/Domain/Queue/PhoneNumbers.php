<?php

namespace App\Domain\Queue;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/** Validates and normalizes phone numbers to E.164 (design Decision 13). */
class PhoneNumbers
{
    public function normalize(?string $input, string $defaultRegion = 'US'): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($input, strtoupper($defaultRegion));
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($number)) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    public function isMobileCapable(string $e164): bool
    {
        $util = PhoneNumberUtil::getInstance();
        $type = $util->getNumberType($util->parse($e164));

        return in_array($type, [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true);
    }

    /** "+1 202-555-0123" style for display; masked for staff lists when needed. */
    public function display(?string $e164): string
    {
        if ($e164 === null) {
            return '';
        }

        $util = PhoneNumberUtil::getInstance();

        return $util->format($util->parse($e164), PhoneNumberFormat::INTERNATIONAL);
    }
}
