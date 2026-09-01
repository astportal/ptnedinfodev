<?php

/**
 * แปลงไฟล์ประชากรรายอายุจาก stat.bora.dopa.go.th (pipe-delimited, ดาวน์โหลดจากกรมการปกครอง — คนละ
 * แหล่งข้อมูลกับฟอร์มสำรวจ 1-16 ที่หน่วยงานกรอกส่ง) ให้เป็นยอดประชากรแยกช่วงอายุ x อำเภอ พร้อมกันคนละ
 * ครั้งไม่ให้นับซ้ำ/นับขาดจากโครงสร้างไฟล์ที่ซับซ้อนกว่าที่เห็นตอนแรก (ดูหัวข้อ "จุดที่พลาดมาก่อน" ด้านล่าง)
 *
 * รูปแบบไฟล์ต้นทาง (1 บรรทัด/1 พื้นที่): ชื่อพื้นที่ | อายุ0_ชาย | อายุ0_หญิง | อายุ1_ชาย | ... |
 * อายุ100_ชาย | อายุ100_หญิง | (หมวดพิเศษอื่น ๆ อีก 4 ชุด ไม่ใช้) | รวมชาย | รวมหญิง | รวมทั้งหมด
 * ตรวจสอบกับไฟล์จริงของจังหวัดปัตตานีแล้ว field ที่ 220 (1-indexed) เป็นยอดรวมทั้งหมดเสมอ
 *
 * โครงสร้างลำดับบรรทัดในไฟล์:
 * 1. บรรทัดแรก = ยอดรวมทั้งจังหวัด ("จังหวัด...")
 * 2. ต่อด้วยบล็อกของแต่ละ "อำเภอ..." เรียงตามลำดับ — แถวอำเภอ + ตำบลลูกที่ตามมาจนกว่าจะเจอ "อำเภอ"
 *    ถัดไป ผลรวมของตำบลลูกในบล็อกนี้ตรงกับยอดในแถวอำเภอเป๊ะ (ตรวจสอบทั้ง 12 อำเภอแล้ว) จึงใช้ค่าจาก
 *    แถว "อำเภอ" ตรง ๆ ได้เลย — **แต่ยอดนี้คือยอดเฉพาะ "นอกเขตเทศบาล" ของอำเภอนั้น ไม่ใช่ยอดเต็ม**
 * 3. ท้ายไฟล์ (หลังบล็อกอำเภอสุดท้าย) มีอีกส่วนแยกต่างหาก ขึ้นต้นด้วย "เทศบาลตำบล.../เทศบาลเมือง..."
 *    ตามด้วยตำบลลูกของเทศบาลนั้น — เป็นยอดประชากร**ในเขตเทศบาล**ของตำบลนั้น ๆ **ต้องบวกเพิ่มเข้าไปเสมอ
 *    แม้ชื่อตำบลจะซ้ำกับที่เห็นในข้อ 2 ก็ตาม** เพราะเป็นคนละกลุ่มประชากรที่ไม่ทับซ้อนกัน (ในเขต
 *    เทศบาล vs นอกเขตเทศบาล ของตำบลเดียวกัน) — **จุดที่เคยเข้าใจผิด**: ตอนแรกคิดว่าชื่อตำบลซ้ำ =
 *    ข้อมูลซ้ำ ต้องข้าม ทำให้ยอดขาดไป 29,076 คน (ตรวจสอบแล้วว่า sum(ยอดอำเภอทั้ง 12) +
 *    sum(ยอดเทศบาลทั้ง 12 กลุ่ม) = ยอดรวมจังหวัดเป๊ะ 744,971 คน แต่ sum(อำเภอ) + sum(เฉพาะตำบลเทศบาล
 *    ที่ชื่อไม่ซ้ำกับบล็อกอำเภอ) ขาดไป 29,076 คน — พิสูจน์ว่าต้องบวกทุกแถวในข้อ 3 เสมอ ไม่มีข้อยกเว้น)
 *    ไฟล์เองไม่บอกว่าตำบลแต่ละกลุ่มในข้อ 3 เป็นของอำเภอไหน ต้องหาเอง — ใช้ชื่อตำบลเทียบกับทำเนียบ
 *    โรงเรียน (schools_master) ปีเดียวกันเป็นตัวจับคู่ตำบล→อำเภอ (ดู buildTambonLookup())
 * 4. ยอดรวมทุกอำเภอหลังบวกครบ ต้อง**เท่ากับยอดรวมจังหวัดในข้อ 1 เป๊ะ** ถ้าไม่เท่า แปลว่ายังมีตำบลที่
 *    หาอำเภอไม่เจอ (โผล่ใน unresolved) หรือไฟล์มีโครงสร้างที่ไม่เคยเจอมาก่อน ต้องแจ้งเตือนเสมอ ห้ามนิ่งเงียบ
 */
class PopulationImporter
{
    /** ช่วงอายุที่ต้องการ (อายุเริ่ม-อายุจบ นับรวมทั้งสองข้าง) — key ตรงกับคอลัมน์ตาราง population_by_age */
    private const AGE_BANDS = [
        'age_3_5'   => [3, 5],
        'age_6_11'  => [6, 11],
        'age_12_14' => [12, 14],
        'age_15_17' => [15, 17],
        'age_18_19' => [18, 19],
    ];

    /** field index (0-indexed หลัง explode('|', ...)) ของยอดรวมทั้งหมดต่อแถว — ตรวจสอบกับไฟล์จริงแล้ว */
    private const TOTAL_FIELD_INDEX = 219;

    /**
     * สร้างตาราง "ตำบล (ไม่มีคำนำหน้า) => อำเภอ (ไม่มีคำนำหน้า)" จากทำเนียบโรงเรียนปีเดียวกัน ใช้หา
     * อำเภอของตำบลที่โผล่ในเขตเทศบาลท้ายไฟล์ประชากร (ดู class doc ข้อ 3) — คืนตารางว่างเปล่า (ไม่
     * error) ถ้ายังไม่เคยอัปโหลดทำเนียบปีนั้น หรือยังไม่รัน migration 003 — ตำบลที่หาไม่เจอจะไปโผล่
     * เป็น "unresolved" ให้แอดมินตรวจเองแทน ไม่ใช่ทำให้อัปโหลดพัง
     *
     * @return array<string,string>
     */
    public static function buildTambonLookup(PDO $db, int $academicYear): array
    {
        $lookup = [];
        try {
            $stmt = $db->prepare(
                'SELECT DISTINCT tambon, amphoe FROM schools_master
                 WHERE academic_year = :y AND tambon IS NOT NULL AND tambon <> "" AND amphoe IS NOT NULL AND amphoe <> ""'
            );
            $stmt->execute(['y' => $academicYear]);
            while ($row = $stmt->fetch()) {
                $tambon = self::stripPrefix(trim((string)$row['tambon']), 'ตำบล');
                $amphoe = self::stripPrefix(trim((string)$row['amphoe']), 'อำเภอ');
                if ($tambon !== '' && $amphoe !== '') {
                    $lookup[$tambon] = $amphoe;
                }
            }
        } catch (Throwable $e) {
            // ยังไม่ได้อัปโหลดทำเนียบ/ยังไม่รัน migration 003 — คืน lookup ว่าง ให้ตำบลในเขตเทศบาลทุก
            // รายการไปโผล่เป็น unresolved แทน (ปลอดภัยกว่าเดา)
        }
        return $lookup;
    }

    /**
     * @param string $rawText เนื้อไฟล์ .txt ดิบทั้งไฟล์จาก stat.bora.dopa.go.th
     * @param array<string,string> $tambonToAmphoe จาก buildTambonLookup()
     * @return array{
     *   byAmphoe: array<string,array<string,int>>,
     *   provinceTotal: int,
     *   addedTotal: int,
     *   addedByAmphoe: array<string,int>,
     *   unresolved: array<int,array{name:string,population:int}>,
     *   warnings: string[]
     * }
     */
    public static function parse(string $rawText, array $tambonToAmphoe): array
    {
        // ตัด BOM (\xEF\xBB\xBF) หน้าไฟล์ถ้ามี — ไฟล์ต้นทางมี BOM ติดมาด้วย
        $rawText = preg_replace('/^\xEF\xBB\xBF/', '', $rawText) ?? $rawText;
        $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];

        $provinceTotal = 0;
        $byAmphoeFields = []; // อำเภอ => field array (0-indexed, int) — เริ่มจากแถว "อำเภอ" (ยอดนอกเขตเทศบาล)
        $addedByAmphoe = [];  // อำเภอ => จำนวนคนในเขตเทศบาลที่บวกเพิ่ม (สำหรับรายงานผล)
        $unresolved = [];
        $warnings = [];
        $inTrailingSection = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $fields = explode('|', $line);
            $name = trim($fields[0] ?? '');
            if ($name === '') {
                continue;
            }

            if (str_starts_with($name, 'จังหวัด')) {
                $provinceTotal = (int)($fields[self::TOTAL_FIELD_INDEX] ?? 0);
                continue;
            }

            if (str_starts_with($name, 'อำเภอ')) {
                $amphoeName = self::stripPrefix($name, 'อำเภอ');
                $byAmphoeFields[$amphoeName] = self::toIntFields($fields);
                continue;
            }

            if (str_starts_with($name, 'เทศบาล')) {
                // ไม่ใช้ค่าของแถวนี้เอง (ใช้ค่าของตำบลลูกแต่ละแถวที่ตามมาแทน — ดู class doc ข้อ 3)
                $inTrailingSection = true;
                continue;
            }

            if (str_starts_with($name, 'ตำบล')) {
                if (!$inTrailingSection) {
                    // อยู่ในบล็อกอำเภอหลัก (ข้อ 2) ค่าถูกนับรวมอยู่ในแถว "อำเภอ" ของบล็อกนี้แล้ว ข้าม
                    continue;
                }
                // ตำบลในเขตเทศบาล (ข้อ 3) — เป็นคนละกลุ่มประชากรกับตำบลชื่อเดียวกันในข้อ 2 เสมอ (ดู
                // class doc) ต้องบวกเพิ่มทุกแถวไม่มีข้อยกเว้น หาว่าเป็นของอำเภอไหนจากทำเนียบโรงเรียน
                $bareTambon = self::stripPrefix($name, 'ตำบล');
                $resolvedAmphoe = $tambonToAmphoe[$bareTambon] ?? null;
                $population = (int)($fields[self::TOTAL_FIELD_INDEX] ?? 0);
                if ($resolvedAmphoe === null || !isset($byAmphoeFields[$resolvedAmphoe])) {
                    $unresolved[] = ['name' => $name, 'population' => $population];
                    continue;
                }
                $intFields = self::toIntFields($fields);
                foreach ($intFields as $i => $v) {
                    $byAmphoeFields[$resolvedAmphoe][$i] = ($byAmphoeFields[$resolvedAmphoe][$i] ?? 0) + $v;
                }
                $addedByAmphoe[$resolvedAmphoe] = ($addedByAmphoe[$resolvedAmphoe] ?? 0) + $population;
                continue;
            }

            // ชื่อพื้นที่รูปแบบอื่นที่ไม่รู้จัก (ไม่เคยเจอในไฟล์จริงที่ตรวจสอบ) — ข้ามเงียบ ๆ ไม่ error
            // เพราะไม่กระทบยอดอำเภอที่ต้องการ แต่จะโผล่เป็นส่วนต่างในการตรวจสอบยอดรวมท้ายฟังก์ชัน
        }

        $byAmphoe = [];
        foreach ($byAmphoeFields as $amphoeName => $fields) {
            $bands = [];
            foreach (self::AGE_BANDS as $bandKey => [$ageFrom, $ageTo]) {
                $male = 0;
                $female = 0;
                for ($age = $ageFrom; $age <= $ageTo; $age++) {
                    $male += $fields[1 + 2 * $age] ?? 0;
                    $female += $fields[2 + 2 * $age] ?? 0;
                }
                $bands["{$bandKey}_male"] = $male;
                $bands["{$bandKey}_female"] = $female;
            }
            $bands['total'] = $fields[self::TOTAL_FIELD_INDEX] ?? 0;
            $byAmphoe[$amphoeName] = $bands;
        }

        $addedTotal = array_sum($addedByAmphoe);
        $sumTotal = array_sum(array_column($byAmphoe, 'total'));
        if ($provinceTotal > 0 && $sumTotal !== $provinceTotal) {
            $diff = $provinceTotal - $sumTotal;
            $warnings[] = "ยอดรวมทุกอำเภอหลังปรับ ({$sumTotal} คน) ไม่เท่ากับยอดรวมทั้งจังหวัดในไฟล์ "
                . "({$provinceTotal} คน) ต่างกัน {$diff} คน — ตรวจสอบรายการ \"หาอำเภอไม่เจอ\" ด้านล่าง "
                . "(มักเกิดจากทำเนียบโรงเรียนของปีนี้ไม่มีตำบลนั้น หรือไฟล์มีโครงสร้างที่ระบบยังไม่เคยเจอ)";
        }
        if (!$byAmphoe) {
            $warnings[] = 'ไม่พบข้อมูลระดับอำเภอในไฟล์นี้เลย — ตรวจสอบว่าเป็นไฟล์รูปแบบเดียวกับที่ระบบรองรับ';
        }

        return [
            'byAmphoe'      => $byAmphoe,
            'provinceTotal' => $provinceTotal,
            'addedTotal'    => $addedTotal,
            'addedByAmphoe' => $addedByAmphoe,
            'unresolved'    => $unresolved,
            'warnings'      => $warnings,
        ];
    }

    /** @return array<int,int> */
    private static function toIntFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $i => $v) {
            $out[$i] = is_numeric($v) ? (int)$v : 0;
        }
        return $out;
    }

    /** ตัดคำนำหน้า (เช่น "อำเภอ"/"ตำบล") ออกจากชื่อพื้นที่แบบ byte-safe (UTF-8 ไม่ต้องพึ่ง mbstring) */
    private static function stripPrefix(string $name, string $prefix): string
    {
        if (str_starts_with($name, $prefix)) {
            return trim(substr($name, strlen($prefix)));
        }
        return trim($name);
    }
}
