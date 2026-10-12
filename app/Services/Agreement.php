<?php
declare(strict_types=1);

namespace App\Services;

/** The iERA Volunteer Agreement (version 1.0) signed at onboarding stage 4. */
final class Agreement
{
    public const VERSION = '1.0';

    /** @return array<int, array{heading:string, body:string, bullets:string[]}> */
    public static function sections(): array
    {
        return [
            ['heading' => 'Volunteer Agreement', 'body' => 'This Agreement is made between the Islamic Education and Research Academy ("iERA") and the undersigned Volunteer ("Volunteer").', 'bullets' => []],
            ['heading' => 'Commitment', 'body' => 'The Volunteer will perform their duties to a reasonable standard and notify iERA of any changes in availability.', 'bullets' => []],
            ['heading' => 'Standards of Conduct', 'body' => 'The Volunteer agrees to:', 'bullets' => [
                "Act professionally, respectfully and in line with iERA's values",
                'Comply with all applicable laws and iERA policies',
                'Treat all individuals fairly and without discrimination',
                "Avoid any conduct that may harm iERA's reputation",
                'Maintain appropriate dress, language and conduct at all times.',
            ]],
            ['heading' => 'Confidentiality & Data Protection', 'body' => "The Volunteer agrees to treat all information encountered during their activities with iERA as strictly confidential. No confidential information, including any personal or sensitive data relating to individuals, may be shared with any third party or used outside of volunteer activities unless explicit prior permission has been given.\n\nConfidential information will only be accessed, collected, or used where there is a clear and lawful basis, such as consent, legal obligation, or where necessary for the delivery of agreed activities.\n\nThe Volunteer will follow iERA's data protection policies and procedures at all times and will ensure that any personal data is handled responsibly, kept secure (whether stored electronically or physically), and only retained for as long as necessary before being securely deleted.\n\nAny suspected or actual data breach must be reported immediately in accordance with iERA's reporting procedures and legal obligations.", 'bullets' => []],
            ['heading' => 'Safeguarding', 'body' => 'The Volunteer acknowledges to protect the welfare of all individuals, agrees to report any concerns and maintain appropriate professional boundaries at all times.', 'bullets' => []],
            ['heading' => 'Criminal Declaration', 'body' => 'The Volunteer agrees to disclose any criminal convictions or pending charges that may affect their suitability for the volunteer role at iERA.', 'bullets' => []],
            ['heading' => 'Anti-Extremism Clause', 'body' => "The Volunteer acknowledges iERA's commitment to preventing extremism and agrees to uphold its principles at all times. The Volunteer shall not promote, advocate or endorse hatred, violence, terrorism or unlawful activity, nor express support for any proscribed organisations. All conduct, communications and representations must reflect respect, tolerance and compliance with applicable laws.\n\nWhere applicable, the Volunteer agrees to ensure that any religious or public communication is presented responsibly, in proper context and in a manner appropriate to the audience, avoiding any misinterpretation that could incite harm or division. The Volunteer further agrees not to promote political parties, external causes or fundraising activities during their volunteering services without prior written approval from iERA.", 'bullets' => []],
            ['heading' => 'Intellectual Property', 'body' => 'Any work produced during volunteering remains the property of iERA.', 'bullets' => []],
            ['heading' => 'Social Media', 'body' => "The Volunteer agrees to use social media responsibly, respectfully and in alignment with iERA's values, refraining from any activities that could harm iERA's reputation. The volunteer agrees to avoid commenting on social issues in relation to iERA and will not publicise any information that they have come across during the course of their volunteering services.", 'bullets' => []],
            ['heading' => 'Liability', 'body' => 'The Volunteer shall indemnify and hold iERA harmless from any claims, damages, losses or expenses arising out of their voluntary services, except in cases of gross negligence or intentional misconduct.', 'bullets' => []],
            ['heading' => 'Termination', 'body' => "Any breach of this clause may be treated as serious misconduct and may result in immediate termination of the volunteer's engagement.", 'bullets' => []],
        ];
    }

    /** Plain-text snapshot stored immutably with each signature. */
    public static function snapshot(): string
    {
        $out = [];
        foreach (self::sections() as $s) {
            $t = $s['heading'] . "\n";
            if ($s['body'] !== '') {
                $t .= $s['body'] . "\n";
            }
            if ($s['bullets']) {
                $t .= implode("\n", array_map(fn($b) => '  — ' . $b, $s['bullets'])) . "\n";
            }
            $out[] = $t;
        }
        return implode("\n", $out);
    }

    public static function normalizeName(string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? $s));
    }
}
