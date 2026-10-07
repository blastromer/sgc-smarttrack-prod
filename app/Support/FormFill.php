<?php

namespace App\Support;

use App\Models\SchoolFormProfile;
use App\Models\User;
use ZipArchive;

class FormFill
{
    /**
     * Copy an official DOCX, stamp packet identity, and fill DepEd letterhead placeholders from Form data.
     */
    public static function docx(User $user, string $sourcePath): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sgcf');
        if ($tmp === false || ! copy($sourcePath, $tmp)) {
            throw new \RuntimeException('Could not prepare a filled template.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Could not open the Word template.');
        }

        $subs = self::substitutions($user);
        $chairs = array_values(array_filter([
            self::composed($user)['co_chair_elected'],
            self::composed($user)['co_chair_designated'],
        ], fn ($value) => filled($value)));

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || ! preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $name)) {
                continue;
            }

            $xml = $zip->getFromIndex($i);
            if (! is_string($xml)) {
                continue;
            }

            if ($name === 'word/document.xml') {
                $stamped = preg_replace('/(<w:body[^>]*>)/', '$1'.self::headerXml(self::fields($user)), $xml, 1);
                $xml = is_string($stamped) ? $stamped : $xml;
            }

            $xml = self::applySubstitutions($xml, $subs, $chairs);
            $zip->addFromString($name, $xml);
        }

        $zip->close();

        return $tmp;
    }

    /**
     * @return array<string, string>
     */
    public static function fields(User $user): array
    {
        $composed = self::composed($user);
        $assessment = AssessmentEngine::forSchool($user);
        $team = User::schoolTeam($user->school_code);
        $head = $team->firstWhere('role', 'school_head');
        $encoder = $user->isEncoder()
            ? $user
            : $team->firstWhere('role', 'school');

        $fields = [
            'School' => $composed['school_name'] ?: ($user->school_name ?: ($head?->school_name ?: '—')),
            'School ID' => $user->school_code ?: ($head?->school_code ?: '—'),
            'Region' => $composed['region'],
            'Division' => $composed['division'],
            'Address' => $composed['school_address'],
            'School year' => $composed['school_year'],
            'Cycle' => $assessment?->cycle?->name ?: 'No open cycle',
            'Level' => $assessment?->cycle?->level ?: '—',
            'Date prepared' => now()->format('F j, Y'),
            'School Head' => $composed['school_head_name'] ?: trim(($head?->name ?? '').(($head?->position) ? ' · '.$head->position : '')),
            'Elected Co-Chair' => $composed['co_chair_elected'],
            'Designated Co-Chair' => $composed['co_chair_designated'],
            'SGC Secretary' => $composed['secretary_name'],
            'Encoder' => trim(($encoder?->name ?? '').(($encoder?->position) ? ' · '.$encoder->position : '')),
            'Contact' => $composed['contact'],
            'Email' => $composed['email'],
            'Venue' => $composed['venue'],
            'Meeting subject' => $composed['meeting_subject'],
            'Meeting date' => $composed['meeting_datetime'],
        ];

        return array_filter($fields, fn ($value) => filled($value));
    }

    /**
     * @return array<string, string>
     */
    public static function composed(User $user): array
    {
        $profile = SchoolFormProfile::forSchool($user);
        $form = $profile->toForm($user);
        $year = trim($form['school_year']);
        $form['school_year_sy'] = $year === ''
            ? ''
            : (preg_match('/s\\/y/i', $year) ? $year : 'S/Y '.$year);

        return $form;
    }

    /**
     * @return array<string, string>
     */
    public static function substitutions(User $user): array
    {
        $v = self::composed($user);
        $map = [
            '[SCHOOL NAME]' => $v['school_name'],
            '[SCHOOL ADDRESS]' => $v['school_address'],
            '[OFFICE ADDRESS]' => $v['school_address'],
            '[EMAIL ADDRESS]' => $v['email'],
            '[CONTACT DETAILS]' => $v['contact'],
            '[NAME AND SIGNATURE OF SENDER OFFICE HEAD]' => $v['school_head_name'],
            '[insert full name &amp; signature, SGC Secretary]' => $v['secretary_name'],
            '[insert full name &amp; signature, SGC Elected Co-Chairperson]' => $v['co_chair_elected'],
            '[insert full name &amp; signature, SGC Designated Co-Chairperson]' => $v['co_chair_designated'],
            '[Full name &amp; signature, SGC Secretary]' => $v['secretary_name'],
            '[SGC Elected Co-Chairperson]' => $v['co_chair_elected'],
            '[SGC Designated Co-Chairperson]' => $v['co_chair_designated'],
            '[School Principal]' => $v['school_head_name'],
            '[SECRETARY NAME]' => $v['secretary_name'],
            '[INSERT VENUE/MODE]' => $v['venue'],
            '[INSERT SUBJECT]' => $v['meeting_subject'],
            '[INSERT DATE]' => $v['meeting_datetime'],
            '[Insert brief description of the purpose of the meeting]' => $v['meeting_purpose'],
            '[School Name]' => $v['school_name'],
            '[name of school]' => $v['school_name'],
            '[SCHOOL]' => $v['school_name'],
            '[ADDRESS]' => $v['school_address'],
            '[REGION]' => $v['region'],
            '[Region]' => $v['region'],
            '[DIVISION]' => $v['division'],
            '[ Division]' => $v['division'],
            '[Division]' => $v['division'],
            'S/Y__-__' => $v['school_year_sy'],
            '(##) ###-####' => $v['contact'],
        ];

        $pairs = [];
        foreach ($map as $search => $value) {
            if (filled($value)) {
                $pairs[$search] = (string) $value;
            }
        }

        uksort($pairs, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $pairs;
    }

    /**
     * @param  array<string, string>  $subs
     * @param  list<string>  $chairs
     */
    private static function applySubstitutions(string $xml, array $subs, array $chairs): string
    {
        foreach ($subs as $search => $value) {
            $xml = self::replaceInXml($xml, $search, $value, 0);
        }

        foreach ($chairs as $value) {
            $xml = self::replaceInXml($xml, '[NAME AND SIGNATURE OF CO-CHAIRPERSON]', $value, 1);
        }

        return $xml;
    }

    /**
     * @param  array<string, string>  $fields
     */
    private static function headerXml(array $fields): string
    {
        $xml = self::p('SGC SmartTrack — auto-filled school data', true);
        $xml .= self::p('Values come from Form data and your account. Complete remaining blanks in Word, then upload. Attaching a Minimum MOV encodes Yes for that FI.');
        foreach ($fields as $label => $value) {
            $xml .= self::p($label.': '.$value);
        }
        $xml .= self::p('');

        return $xml;
    }

    private static function replaceInXml(string $xml, string $search, string $replace, int $limit): string
    {
        $safe = htmlspecialchars($replace, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        if ($limit === 1) {
            $pos = strpos($xml, $search);
            if ($pos !== false) {
                return substr_replace($xml, $safe, $pos, strlen($search));
            }

            return self::replaceSplit($xml, $search, $safe, 1);
        }

        if (str_contains($xml, $search)) {
            return str_replace($search, $safe, $xml);
        }

        return self::replaceSplit($xml, $search, $safe, 0);
    }

    private static function replaceSplit(string $xml, string $search, string $safe, int $limit): string
    {
        $chars = preg_split('//u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($chars === []) {
            return $xml;
        }

        $pattern = '';
        foreach ($chars as $i => $ch) {
            $pattern .= preg_quote($ch, '/');
            if ($i < count($chars) - 1) {
                $pattern .= '(?:<[^>]+>)*';
            }
        }

        $out = preg_replace('/'.$pattern.'/u', $safe, $xml, $limit > 0 ? $limit : -1);

        return is_string($out) ? $out : $xml;
    }

    private static function p(string $text, bool $bold = false): string
    {
        $t = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $rPr = $bold ? '<w:rPr><w:b/></w:rPr>' : '';

        return '<w:p><w:r>'.$rPr.'<w:t xml:space="preserve">'.$t.'</w:t></w:r></w:p>';
    }
}
