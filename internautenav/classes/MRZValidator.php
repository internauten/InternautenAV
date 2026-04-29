<?php

class MrzValidator
{
    private const DOC_CH_ID = 'ch_id';
    private const DOC_CH_PASS = 'ch_pass';
    private const DOC_EU_PASS = 'eu_pass';

    public static function validate($docType, $line1, $line2, $line3 = '')
    {
        $docType = (string) $docType;

        if (!in_array($docType, [self::DOC_CH_ID, self::DOC_CH_PASS, self::DOC_EU_PASS], true)) {
            return self::invalid('Bitte Dokumenttyp waehlen.');
        }

        if ($docType === self::DOC_CH_ID) {
            $l1 = self::normalizeLine($line1, 30);
            $l2 = self::normalizeLine($line2, 30);
            $l3 = self::normalizeLine($line3, 30);
            if ($l1 === null || $l2 === null || $l3 === null) {
                return self::invalid('CH ID erwartet 3 MRZ-Zeilen mit je 30 Zeichen.');
            }

            return self::parseTd1($l1, $l2, $l3);
        }

        $l1 = self::normalizeLine($line1, 44);
        $l2 = self::normalizeLine($line2, 44);
        if ($l1 === null || $l2 === null) {
            return self::invalid('Pass erwartet 2 MRZ-Zeilen mit je 44 Zeichen.');
        }

        return self::parseTd3($l1, $l2);
    }

    public static function isAdult(DateTimeImmutable $birthDate, $minimumAge)
    {
        $minimumAge = (int) $minimumAge;
        $now = new DateTimeImmutable('now');
        $threshold = $birthDate->modify('+' . $minimumAge . ' years');

        if ($threshold > $now) {
            return [
                'valid' => false,
                'message' => 'Mindestalter nicht erreicht.',
            ];
        }

        return [
            'valid' => true,
        ];
    }

    public static function matchNames($firstname, $lastname, array $mrzData)
    {
        $shippingFirst = self::normalizeNameCompact((string) $firstname);
        $shippingLast = self::normalizeNameCompact((string) $lastname);

        $mrzFirst = self::normalizeNameCompact((string) $mrzData['given_names']);
        $mrzLast = self::normalizeNameCompact((string) $mrzData['surname']);

        if ($shippingFirst === '' || $shippingLast === '' || $mrzFirst === '' || $mrzLast === '') {
            return [
                'valid' => false,
                'message' => 'Name konnte nicht eindeutig geprueft werden.',
            ];
        }

        $firstMatches = ($shippingFirst === $mrzFirst) || (strpos($mrzFirst, $shippingFirst) !== false);
        $lastMatches = ($shippingLast === $mrzLast) || (strpos($mrzLast, $shippingLast) !== false) || (strpos($shippingLast, $mrzLast) !== false);

        if (!$firstMatches || !$lastMatches) {
            return [
                'valid' => false,
                'message' => 'Name stimmt nicht mit Lieferadresse ueberein.',
            ];
        }

        return [
            'valid' => true,
        ];
    }

    private static function parseTd3($line1, $line2)
    {
        if (substr($line1, 0, 1) !== 'P') {
            return self::invalid('MRZ ungueltig: Pass muss mit P beginnen.');
        }

        $birthRaw = substr($line2, 13, 6);
        $birthCheckDigit = substr($line2, 19, 1);
        if (!self::validateCheckDigit($birthRaw, $birthCheckDigit)) {
            return self::invalid('MRZ ungueltig: Pruefziffer Geburtsdatum stimmt nicht.');
        }

        $birthDate = self::parseBirthDate($birthRaw);
        if ($birthDate === null) {
            return self::invalid('MRZ ungueltig: Geburtsdatum konnte nicht gelesen werden.');
        }

        [$surname, $givenNames] = self::extractNames(substr($line1, 5));
        if ($surname === '' || $givenNames === '') {
            return self::invalid('MRZ ungueltig: Name/Vorname konnte nicht gelesen werden.');
        }

        return [
            'valid' => true,
            'data' => [
                'birth_date' => $birthDate,
                'birth_iso' => $birthDate->format('Y-m-d'),
                'surname' => $surname,
                'given_names' => $givenNames,
            ],
        ];
    }

    private static function parseTd1($line1, $line2, $line3)
    {
        $birthRaw = substr($line2, 0, 6);
        $birthCheckDigit = substr($line2, 6, 1);
        if (!self::validateCheckDigit($birthRaw, $birthCheckDigit)) {
            return self::invalid('MRZ ungueltig: Pruefziffer Geburtsdatum stimmt nicht.');
        }

        $birthDate = self::parseBirthDate($birthRaw);
        if ($birthDate === null) {
            return self::invalid('MRZ ungueltig: Geburtsdatum konnte nicht gelesen werden.');
        }

        [$surname, $givenNames] = self::extractNames($line3);
        if ($surname === '' || $givenNames === '') {
            return self::invalid('MRZ ungueltig: Name/Vorname konnte nicht gelesen werden.');
        }

        return [
            'valid' => true,
            'data' => [
                'birth_date' => $birthDate,
                'birth_iso' => $birthDate->format('Y-m-d'),
                'surname' => $surname,
                'given_names' => $givenNames,
            ],
        ];
    }

    private static function normalizeLine($line, $expectedLength)
    {
        $line = strtoupper(trim((string) $line));
        $line = str_replace(' ', '<', $line);

        if (strlen($line) !== (int) $expectedLength) {
            return null;
        }

        if (!preg_match('/^[A-Z0-9<]+$/', $line)) {
            return null;
        }

        return $line;
    }

    private static function parseBirthDate($yymmdd)
    {
        if (!preg_match('/^\d{6}$/', $yymmdd)) {
            return null;
        }

        $yy = (int) substr($yymmdd, 0, 2);
        $mm = (int) substr($yymmdd, 2, 2);
        $dd = (int) substr($yymmdd, 4, 2);

        $currentYY = (int) (new DateTimeImmutable('now'))->format('y');
        $century = ($yy > $currentYY) ? 1900 : 2000;
        $year = $century + $yy;

        if (!checkdate($mm, $dd, $year)) {
            return null;
        }

        return DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $mm, $dd));
    }

    private static function extractNames($raw)
    {
        $parts = explode('<<', (string) $raw, 2);
        $surname = self::normalizeNameReadable($parts[0]);
        $given = isset($parts[1]) ? self::normalizeNameReadable($parts[1]) : '';

        return [$surname, $given];
    }

    private static function normalizeNameReadable($value)
    {
        $value = str_replace('<', ' ', (string) $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return (string) $value;
    }

    private static function normalizeNameCompact($value)
    {
        $value = strtoupper((string) $value);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted)) {
                $value = $converted;
            }
        }

        $value = preg_replace('/[^A-Z]/', '', $value);

        return (string) $value;
    }

    private static function validateCheckDigit($field, $checkDigitChar)
    {
        if (!preg_match('/^[0-9<]$/', (string) $checkDigitChar)) {
            return false;
        }

        $expected = self::computeCheckDigit($field);
        if ($checkDigitChar === '<') {
            return $expected === 0;
        }

        return (int) $checkDigitChar === $expected;
    }

    private static function computeCheckDigit($field)
    {
        $weights = [7, 3, 1];
        $sum = 0;
        $field = (string) $field;
        $length = strlen($field);

        for ($i = 0; $i < $length; $i++) {
            $sum += self::mrzCharValue($field[$i]) * $weights[$i % 3];
        }

        return $sum % 10;
    }

    private static function mrzCharValue($char)
    {
        if ($char === '<') {
            return 0;
        }

        if ($char >= '0' && $char <= '9') {
            return (int) $char;
        }

        if ($char >= 'A' && $char <= 'Z') {
            return ord($char) - 55;
        }

        return 0;
    }

    private static function invalid($message)
    {
        return [
            'valid' => false,
            'message' => (string) $message,
        ];
    }
}
