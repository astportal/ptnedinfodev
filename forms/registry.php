<?php
/**
 * Form registry — the ONLY place needed to add support for a new form/sheet.
 *
 * Each entry describes one sheet of one Excel template:
 *   form_key        : unique id used in the database and URLs, e.g. "1_agency"
 *   form_label      : human label shown in the UI
 *   sheet_name      : exact sheet name inside the .xlsx (must match the template)
 *   header_rows     : how many rows (from the top) are header rows, total — including title
 *                      rows, blank spacer rows, and every header level.
 *   skip_rows       : array of row numbers (within header_rows) to exclude when building column
 *                      labels — table titles, blank spacer rows, or a redundant broader-category
 *                      row that a deeper row already restates in full (e.g. row 3 says "ประถม" and
 *                      row 4 already says "ประถมศึกษาปีที่ 1" — row 3 would just be noise).
 *   identity_cols   : how many leading columns are "identity" columns (ลำดับที่ / รหัสสถานศึกษา / ชื่อหน่วยงาน ฯลฯ)
 *                     — these become fixed fields on the submission row instead of generic value columns.
 *   identity_fields : field name for each identity column, in order (must match identity_cols count)
 *   value_type      : 'numeric' (default) or 'text'. When 'numeric', every value column is checked:
 *                      "-" is treated as 0, "/" is treated as 1, plain numbers pass through as-is,
 *                      and anything else is flagged as needs_review instead of being guessed (see
 *                      Importer::classifyValue). Use 'text' for sheets whose value columns are genuinely
 *                      free text / categorical (names, sizes, types) — no numeric inference is applied.
 *   value_label     : optional. The "tidy" export (for Excel PivotTable) turns every header level
 *                      of a column into one combined, readable label (e.g. "ประถมศึกษาปีที่ 1") in a
 *                      single column — this names that column (e.g. "ชั้นปี"). Defaults to "รายการ".
 *   value_split_last : optional. Pulls the last header level out into its own column with this
 *                      name (e.g. "เพศ") instead of joining it into value_label — use when the last
 *                      level is a genuinely independent dimension (ชาย/หญิง) that reads better on
 *                      its own than glued onto the category name.
 *   merge_extra_columns_into : optional, requires value_type 'numeric'. Set to a column_path text
 *                      (e.g. "อื่นๆ") when the template explicitly allows agencies to append extra
 *                      columns of their own past a fixed last column — instead of blocking the whole
 *                      sheet on a column-count mismatch, any trailing columns after that anchor get
 *                      summed into it. Every column up to and including the anchor must still match
 *                      the reference template exactly, or the normal structure error still fires.
 *
 * Everything after identity_cols is stored generically as (column_path => value), where column_path
 * is the header text of every non-skipped header row for that column, joined with " / ". This is what
 * lets the SAME importer support every double/triple-header report table without hand-mapping columns.
 * A header cell left blank to visually group with its neighbour (with or without an actual Excel
 * merge) inherits the last non-blank text seen in that same header row.
 *
 * To add a new form: inspect the template's header rows, add one array entry below, done.
 */

// คอลัมน์ระบุตัวตนมาตรฐานที่พบในเกือบทุกฟอร์ม (ลำดับที่, รหัสสถานศึกษา, สังกัด, ชื่อ, อำเภอ, ตำบล)
$stdIdentity = ['seq_no', 'school_code', 'agency_name', 'school_name', 'amphoe', 'tambon'];

return [

    '1_agency' => [
        'form_label'     => 'ตารางที่ 1 ข้อมูลพื้นฐานหน่วยงานต้นสังกัดสถานศึกษา',
        'source_file'    => '1_ข้อมูลพื้นฐานหน่วยงานต้นสังกัดสถานศึกษา.xlsx',
        'sheets' => [
            [
                'sheet_name'      => 'ข้อมูลพื้นฐานหน่วยงาน',
                'skip_rows'       => [1],
                'header_rows'     => 4,
                'identity_cols'   => 3,
                'identity_fields' => ['agency_name', 'admin_name', 'phone'],
                'value_type'      => 'numeric', // จำนวนบุคลากรแยกชาย/หญิง
                'value_label'     => 'ประเภทบุคลากร', // เช่น "ข้าราชการ"
                'value_split_last' => 'เพศ', // แยก ชาย/หญิง ออกมาเป็นอีกคอลัมน์
            ],
        ],
    ],

    '2_school_basic' => [
        'form_label'     => 'ตารางที่ 2 ข้อมูลพื้นฐานสถานศึกษา',
        'source_file'    => '2_ข้อมูลพื้นฐานสถานศึกษา.xlsx',
        'sheets' => [
            [
                'sheet_name'      => 'ข้อมูลสถานศึกษา',
                'skip_rows'       => [1],
                'header_rows'     => 3,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'text', // ชื่อผู้บริหาร/โทรศัพท์/ขนาด/ประเภท เป็นข้อความ ไม่ใช่ตัวเลข
            ],
            [
                'sheet_name'      => 'ระดับที่เปิดสอน',
                'skip_rows'       => [1],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // ติ๊ก "/" = เปิดสอน, "-" = ไม่เปิดสอน ในแต่ละระดับชั้น
                'value_label'     => 'ระดับชั้น',
            ],
        ],
    ],

    '3_classrooms' => [
        'form_label'     => 'ตารางที่ 3 จำนวนห้องเรียน',
        'source_file'    => '3_จำนวนห้องเรียน.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '3.จำนวนห้องเรียน',
                'skip_rows'       => [1],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนห้องเรียนแยกตามระดับชั้น
                'value_label'     => 'ชั้นปี', // เช่น "ประถมศึกษาปีที่ 1"
            ],
        ],
    ],

    '4_students' => [
        'form_label'     => 'ตารางที่ 4 จำนวนผู้เรียน',
        'source_file'    => '4_จำนวนนักเรียน.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '4.จำนวนผู้เรียน',
                'skip_rows'       => [1],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้เรียนแยกตามระดับชั้นและเพศ
                'value_label'     => 'ชั้นปี',
                'value_split_last' => 'เพศ',
            ],
        ],
    ],

    '5_disadvantaged' => [
        'form_label'     => 'ตารางที่ 5 จำนวนนักเรียนด้อยโอกาส',
        'source_file'    => '5_จำนวนนักเรียนด้อยโอกาส.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '5.1 นักเรียนด้อยโอกาส',
                'skip_rows'       => [1, 2],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนนักเรียนด้อยโอกาสแยกตามระดับชั้นและเพศ
                'value_label'     => 'ชั้นปี',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '5.2 ประเภทความด้อยโอกาส',
                'skip_rows'       => [1, 2],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนแยกตามประเภทความด้อยโอกาสและเพศ
                'value_label'     => 'ประเภทความด้อยโอกาส',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '5.3 ได้รับการช่วยเหลือ',
                // แถว 3 เป็นหมวดกว้าง (เช่น "ประถม") ที่ซ้ำซ้อนกับแถว 4 ซึ่งเขียนชื่อเต็มอยู่แล้ว
                // (เช่น "ประถมศึกษาปีที่ 1") จึงข้ามแถว 3 ไปด้วยเพื่อไม่ให้ชื่อคอลัมน์ซ้ำคำ
                'skip_rows'       => [1, 2, 3],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนนักเรียนด้อยโอกาสที่ได้รับการช่วยเหลือ แยกตามระดับชั้นและเพศ
                'value_label'     => 'ชั้นปี',
                'value_split_last' => 'เพศ',
            ],
        ],
    ],

    '6_foreign_nationality' => [
        'form_label'     => 'ตารางที่ 6 จำนวนนักเรียนต่างสัญชาติ',
        'source_file'    => '6_จำนวนนักเรียนต่างสัญชาติ.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '6.จำนวนนักเรียนต่างสัญชาติ',
                'skip_rows'       => [1],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนนักเรียนต่างสัญชาติแยกตามระดับชั้นและเพศ
                'value_label'     => 'ชั้นปี',
                'value_split_last' => 'เพศ',
            ],
        ],
    ],

    '7_dropout' => [
        'form_label'     => 'ตารางที่ 7 จำนวนนักเรียนออกกลางคัน',
        'source_file'    => '7_จำนวนนักเรียนออกกลางคัน.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '7.1ออกกลางคัน ประถม ',
                'skip_rows'       => [1, 2],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนออกกลางคันแยกตามชั้นปี สาเหตุ และเพศ
                'value_label'     => 'ชั้นปี/สาเหตุ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '7.2ออกกลางคัน มัธยมต้น',
                'skip_rows'       => [1, 2],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric',
                'value_label'     => 'ชั้นปี/สาเหตุ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '7.3ออกกลางคัน มัธยมปลาย',
                'skip_rows'       => [1, 2],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric',
                'value_label'     => 'ชั้นปี/สาเหตุ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '7.4ออกกลางคัน วิชาชีพ',
                'skip_rows'       => [1, 2],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric',
                'value_label'     => 'ชั้นปี/สาเหตุ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '7.4ออกกลางคัน ปวส.',
                'skip_rows'       => [1, 2],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric',
                'value_label'     => 'ชั้นปี/สาเหตุ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '7.5ออกกลางคัน ป.ตรี',
                'skip_rows'       => [1, 2],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric',
                'value_label'     => 'ชั้นปี/สาเหตุ',
                'value_split_last' => 'เพศ',
            ],
        ],
    ],

    '8_disability' => [
        'form_label'     => 'ตารางที่ 8 จำนวนนักเรียนพิการ',
        'source_file'    => '8_จำนวนนักเรียนพิการ.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '8.1 นักเรียนพิการ',
                'skip_rows'       => [1, 2],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนนักเรียนพิการแยกตามชั้นปีและเพศ
                'value_label'     => 'ชั้นปี',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '8.2 ประเภทความพิการ',
                // แถว 3 เป็นหมวดกว้าง "ประเภทความพิการ" ซ้ำทุกคอลัมน์ ไม่ช่วยแยกแยะ จึงข้ามไป
                'skip_rows'       => [1, 2, 3],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนแยกตามประเภทความพิการและเพศ (มีคอลัมน์ "รวม" ท้ายตารางด้วย)
                'value_label'     => 'ประเภทความพิการ',
                'value_split_last' => 'เพศ',
            ],
        ],
    ],

    '9_graduates' => [
        'form_label'     => 'ตารางที่ 9 ข้อมูลผู้สำเร็จการศึกษา',
        'source_file'    => '9_ข้อมูลผู้สำเร็จการศึกษา.xlsx',
        // หมายเหตุ: ชีท "9.สรุป" ไม่รองรับ — เป็นแบบสรุปที่กรอกครั้งเดียวต่อหน่วยงาน (มีช่อง
        // "หน่วยงาน..." ให้เติมคำเอง ไม่ใช่คอลัมน์ตาราง) และมีแค่ 3 แถวคงที่ ไม่ใช่ 1 แถวต่อโรงเรียน
        // แบบชีทอื่น ๆ จึงไม่เข้ากับ engine ทั่วไปนี้ ต้องออกแบบแยกต่างหากถ้าต้องการใช้งาน
        'sheets' => [
            [
                'sheet_name'      => '9.1ผู้สำเร็จการศึกษา',
                'skip_rows'       => [1, 2],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้สำเร็จการศึกษาแยกตามระดับชั้นและเพศ
                'value_label'     => 'ชั้นปี',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '9.2 ผู้สำเร็จสายอาชีพ',
                'skip_rows'       => [1, 2],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้สำเร็จสายอาชีพแยกตามระดับชั้น สาขาวิชา และเพศ
                'value_label'     => 'ชั้นปี/สาขาวิชา',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '9.3 จบ ป.6',
                // แถว 3 ส่วนใหญ่ซ้ำคำว่า "จำนวนนักเรียน" ไม่ช่วยแยกแยะ ตัวแยกแยะจริงอยู่แถว 4
                'skip_rows'       => [1, 2, 3],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนนักเรียนจบ ป.6 แยกตามสถานะศึกษาต่อและเพศ
                'value_label'     => 'สถานะ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '9.4 จบ ม.3',
                'skip_rows'       => [1, 2, 3],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric',
                'value_label'     => 'สถานะ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '9.5 ม.6',
                'skip_rows'       => [1, 2, 3],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric',
                'value_label'     => 'สถานะ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '9.6 จบมีงานทำ',
                'skip_rows'       => [1, 2],
                'header_rows'     => 5,
                'identity_cols'   => 2,
                'identity_fields' => ['school_code', 'school_name'],
                'value_type'      => 'numeric', // จำนวนผู้สำเร็จการศึกษาที่มีงานทำ แยกตามระดับชั้นและเพศ
                'value_label'     => 'ชั้นปี',
                'value_split_last' => 'เพศ',
            ],
        ],
    ],

    // ปรับโครงสร้างใหม่ทั้งฟอร์ม (2026-08-27) — จาก 5 ชีท (10.1-10.4) เป็น 6 ชีท (10.1-10.6)
    // ชีท 10.3 เดิม (แยกเป็น "ทุกสังกัดยกเว้นสาธิต&อุดม" กับ "เฉพาะสาธิต&อุดมฯ") ถูกยุบรวมเป็น
    // 10.3 เดียว + แยก 10.4 ใหม่สำหรับสาย อว. ต่างหาก, เพิ่ม 10.5 (แยกวุฒิการศึกษา) ใหม่,
    // 10.4 เดิม (วิชาเอก) เลื่อนไปเป็น 10.6 — ตามคำขอผู้ใช้งาน ไม่ต้องรองรับ sheet_name เดิมอีก
    // (ข้อมูลปีเก่าที่เคยนำเข้าด้วยโครงสร้างเดิมจะไม่โผล่ในหน้าเว็บอีกต่อไป แต่ยังอยู่ใน DB)
    '10_teachers' => [
        'form_label'     => 'ตารางที่ 10 ข้อมูลครู',
        'source_file'    => '10_ข้อมูลครูV2.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '10.1ทุกสังกัด',
                'skip_rows'       => [1, 2],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้บริหาร/ครู/บุคลากร แยกตามตำแหน่งและเพศ
                'value_label'     => 'ตำแหน่ง',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '10.2ทุกสังกัด',
                'skip_rows'       => [1, 2],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้สอนแยกตามระดับชั้นที่สอนและเพศ
                'value_label'     => 'ระดับชั้น',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '10.3ครู-แยกตำแหน่ง',
                'skip_rows'       => [1, 2],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนครูแยกตามอันดับ/วิทยฐานะและเพศ
                'value_label'     => 'อันดับ/วิทยฐานะ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '10.4อว-แยกตำแหน่ง',
                'skip_rows'       => [1, 2],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้สอนสาย อว. แยกตามตำแหน่งทางวิชาการและเพศ
                'value_label'     => 'ตำแหน่งทางวิชาการ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '10.5ครู-แยกวุฒิการศึกษา',
                'skip_rows'       => [1, 2],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนครูแยกตามวุฒิการศึกษาสูงสุดและเพศ
                'value_label'     => 'วุฒิการศึกษา',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '10.6ทุกสังกัด',
                // แถว 3 ซ้ำคำว่า "จำนวนผู้สอนแยกตามวิชาเอก" ทุกคอลัมน์ ไม่ช่วยแยกแยะ
                'skip_rows'       => [1, 2, 3],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้สอนแยกตามวิชาเอก (ไม่มีแยกเพศ)
                'value_label'     => 'วิชาเอก',
                // แม่แบบมีหมายเหตุอนุญาตให้ "แทรกคอลัมน์วิชาเอกเพิ่มเติมได้" — ถ้าหน่วยงานเพิ่ม
                // คอลัมน์ต่อท้ายหลัง "อื่นๆ" (คอลัมน์สุดท้าย) ให้รวมค่าเข้ากับ "อื่นๆ" แทนการบล็อก
                // ทั้งชีท (ดู Importer::tryResolveExtraTrailingColumns) — คอลัมน์ก่อนหน้ายังต้องตรง
                // กับต้นฉบับทุกคอลัมน์เหมือนเดิม ไม่งั้นจะ error ตามปกติ
                'merge_extra_columns_into' => 'อื่นๆ',
            ],
        ],
    ],

    '11_nfe' => [
        'form_label'     => 'ตารางที่ 11 ข้อมูลการศึกษา กศน.',
        'source_file'    => '11_ข้อมูลการศึกษา_กศน.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '11.1',
                'skip_rows'       => [1, 2],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้เรียนปกติที่ลงทะเบียน แยกตามกิจกรรมการศึกษาและเพศ
                'value_label'     => 'กิจกรรมการศึกษา',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '11.2',
                'skip_rows'       => [1, 2],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้เรียนพิการที่ลงทะเบียน แยกตามกิจกรรมการศึกษาและเพศ
                'value_label'     => 'กิจกรรมการศึกษา',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '11.3',
                'skip_rows'       => [1, 2],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้เรียนพิการ แยกตามประเภทความพิการและเพศ
                'value_label'     => 'ประเภทความพิการ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '11.4',
                'skip_rows'       => [1, 2],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้เรียนปกติที่สำเร็จการศึกษา แยกตามกิจกรรมการศึกษาและเพศ
                'value_label'     => 'กิจกรรมการศึกษา',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '11.5',
                'skip_rows'       => [1, 2],
                'header_rows'     => 5,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้เรียนพิการที่สำเร็จการศึกษา แยกตามกิจกรรมการศึกษาและเพศ
                'value_label'     => 'กิจกรรมการศึกษา',
                'value_split_last' => 'เพศ',
            ],
        ],
    ],

    '12_project_schools' => [
        'form_label'     => 'ตารางที่ 12 ข้อมูลประเภทโรงเรียนโครงการ',
        'source_file'    => '12_ ข้อมูลประเภทโรงเรียนโครงการ.xlsx',
        // ทุกชีทเป็นรายชื่อโรงเรียนที่เข้าร่วมโครงการนั้น ๆ ล้วน ๆ (ไม่มีคอลัมน์ตัวเลข) —
        // แค่ปรากฏอยู่ในชีทก็คือข้อมูลแล้ว (แปลว่าโรงเรียนนั้นเข้าร่วมโครงการ)
        // หมายเหตุลำดับคอลัมน์: ตำบลมาก่อนอำเภอ ต่างจากฟอร์มอื่นที่อำเภอมาก่อนตำบล
        // แถวที่ 1 ของแต่ละชีท (ก่อน skip) มีชื่อโครงการแบบเต็มอ่านง่ายกว่า sheet_name เอง (เช่น
        // "2. โรงเรียนพระราชดำริที่ได้รับการสนับสนุนโครงการอาหารเช้า" ต่างจากชื่อชีท "2. โครงการอาหารเช้า")
        // — แปะไว้เป็น fixed_extra_identity ทุกแถวของชีทนั้น เพื่อให้ตอนส่งออก/ดูข้อมูลรวมทุกชีท
        // ของฟอร์มนี้เข้าด้วยกัน (เช่น export_tidy.php) ยังบอกได้ว่าแต่ละแถวมาจากโครงการไหน (เดิม
        // มีแค่ sheet_name ในฐานข้อมูล แต่ไม่ได้โผล่เป็นคอลัมน์ในข้อมูลที่ export ออกมา)
        'sheets' => array_map(
            fn($sheet) => [
                'sheet_name'      => $sheet[0],
                'skip_rows'       => [1],
                'header_rows'     => 2,
                'identity_cols'   => 6,
                'identity_fields' => ['seq_no', 'school_code', 'agency_name', 'school_name', 'tambon', 'amphoe'],
                'fixed_extra_identity' => ['project_name' => $sheet[1]],
                'value_type'      => 'text',
            ],
            [
                ['1. พระราชดำริ', '1. โรงเรียนพระราชดำริ'],
                ['2. โครงการอาหารเช้า', '2. โรงเรียนพระราชดำริที่ได้รับการสนับสนุนโครงการอาหารเช้า'],
                ['3.ประชารัฐ', '3. โรงเรียนประชารัฐ(ดีใกล้บ้าน)'],
                ['4.พักนอน', '4.โรงเรียนประชารัฐจังหวัดชายแดนภาคใต้'],
                ['5.รร กองทุน', '5.โรงเรียนกองทุนการศึกษา'],
                ['6.รร.ราชประชานุเคราะห์', '6. โรงเรียนราชประชานุเคราะห์'],
                ['7. ขยายโอกาส', '7. โรงเรียนขยายโอกาสทางการศึกษา'],
                ['8. ในฝัน', '8. โรงเรียนดีประจำอำเภอ(ในฝัน)'],
                ['9.ดีตำบล', '9. โรงเรียนดีประจำตำบล'],
                ['10.พัฒนาคุณภาพ', '10. โรงเรียนพัฒนาคุณภาพประถม-มัธยม'],
                ['11.มาตรฐานสากล', '11. โรงเรียนมาตรฐานสากล'],
                ['12.สเต็ม', '12.โรงเรียนสเต็มศึกษา'],
                ['13. icu', '13.โรงเรียน ICU'],
            ]
        ),
    ],

    '13_age_by_grade' => [
        'form_label'     => 'ตารางที่ 13 จำนวนนักเรียนแยกอายุ รายชั้นเรียน',
        'source_file'    => '13_จำนวนนักเรียนแยกรายชั้น.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '13.นักเรียนแยกอายุรายชั้น',
                // ชีทนี้ต่างจากชีทอื่นทั้งหมด: 1 หน่วยงานกรอกหลายแถว (แถวละ 1 ช่วงอายุ) แทนที่จะ
                // เป็น 1 แถวต่อสถานศึกษา — ชื่อหน่วยงานเขียนไว้แค่แถวแรกของหน่วยงานนั้นแล้วเว้นว่าง
                // ในแถวถัดไป (carry_identity_fields ให้สืบทอดชื่อหน่วยงานลงมาจนกว่าจะเจอชื่อใหม่)
                'skip_rows'       => [1],
                'header_rows'     => 5,
                'identity_cols'   => 2,
                'identity_fields' => ['agency_name', 'age_group'],
                'carry_identity_fields' => ['agency_name'],
                'value_type'      => 'numeric', // จำนวนนักเรียนแยกตามระดับชั้น+เพศ (รวมอยู่ในคอลัมน์เดียวต่อชั้น)
                'value_label'     => 'ระดับชั้น/เพศ',
                // แสดงตารางสรุปยอดรวมทั้งจังหวัดบนหน้าดูข้อมูล แยกแถวตาม extra_identity field นี้
                // (รวมทุกหน่วยงานเข้าด้วยกัน) — ดู view.php ส่วน summary_group_field
                'summary_group_field'       => 'age_group',
                'summary_group_field_label' => 'ช่วงอายุ',
            ],
        ],
    ],

    // เปลี่ยนมาใช้ไฟล์ต้นฉบับเดียว (2026-08-27) — เดิมรวมฟอร์ม 14ก+14ข (คนละไฟล์ คอลัมน์เหมือนกัน)
    // เป็นชุดข้อมูลเดียวผ่าน db_sheet_name ร่วมกัน ตอนนี้ผู้ใช้งานเปลี่ยนมาใช้แบบฟอร์มใหม่ไฟล์เดียว
    // "14_ข้อมูลสถานพัฒนาเด็กปฐมวัย_V2.xlsx" แทนแล้ว (14ก/14ข เดิมเลิกใช้) โครงสร้างคอลัมน์ระบุตัวตน
    // เปลี่ยนเป็นมาตรฐาน ($stdIdentity) ด้วย ไม่มีคอลัมน์ "เป็นศูนย์ถ่ายโอน/ตั้งเอง" อีกต่อไป จึงไม่ต้อง
    // ใช้ extra_identity/fixed_extra_identity แบบเดิมแล้ว — แต่ต้องกลับมาใช้ db_sheet_name/optional
    // อีกครั้ง (2026-08-29) เพราะพบว่าหลายหน่วยงานยังส่งไฟล์ที่ชื่อแท็บชีทเป็น "14.สถานพัฒนาเด็กปฐมวัย"
    // (ชื่อแท็บเดิมจากไฟล์ 14ข เก่า) ทั้งที่เนื้อหาข้างในเป็นโครงสร้างใหม่แล้ว (identity columns ตรง
    // กับ $stdIdentity ทุกตัว ตรวจสอบกับไฟล์จริงหลายหน่วยงานแล้ว) — จึงรับทั้ง 2 ชื่อแท็บ รวมเป็นชุด
    // ข้อมูลเดียวกัน ไม่ให้ไฟล์ที่ไม่ได้เปลี่ยนชื่อแท็บชีท upload ไม่ผ่าน — reference_templates/14
    // ก็มี sheet "14.สถานพัฒนาเด็กปฐมวัย" (สำเนาโครงสร้างเดียวกัน) เพิ่มไว้ให้ตรวจสอบโครงสร้างได้ด้วย
    '14_childcare_centers' => [
        'form_label'     => 'ตารางที่ 14 ข้อมูลสถานพัฒนาเด็กปฐมวัย',
        'source_file'    => '14_ข้อมูลสถานพัฒนาเด็กปฐมวัย_V2.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก',
                'db_sheet_name'   => '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก',
                'optional'        => true, // อัปโหลดไฟล์นี้หรือไฟล์ที่ใช้ชื่อแท็บเก่าก็ได้ ไม่ต้องมีทั้งคู่
                'skip_rows'       => [1],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนเด็กเล็กแยกอายุ/เพศ + จำนวนครูแยกวุฒิ/เพศ
                'value_label'     => 'รายการ',
                'value_split_last' => 'เพศ',
            ],
            [
                // ชื่อแท็บชีทเดิม (จากไฟล์ 14ข เก่า) — โครงสร้างคอลัมน์เหมือนกันทุกประการกับด้านบน
                'sheet_name'      => '14.สถานพัฒนาเด็กปฐมวัย',
                'db_sheet_name'   => '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก',
                'optional'        => true, // อัปโหลดไฟล์นี้หรือไฟล์ที่ใช้ชื่อแท็บใหม่ก็ได้ ไม่ต้องมีทั้งคู่
                'skip_rows'       => [1],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric',
                'value_label'     => 'รายการ',
                'value_split_last' => 'เพศ',
            ],
        ],
    ],

    // เพิ่มเข้ามาใหม่ (2026-08-27) — ไฟล์ 15_ข้อมูลโรงเรียนเอกชนนอกระบบ.xlsx เคยไม่รองรับ (ดูบันทึกเก่า
    // ใน ai_note.md) เพราะ 2 ชีทสุดท้ายมีปัญหาแถวหัวข้อคั่นหมวดปนกับข้อมูลจริง + มีข้อมูลตัวอย่างจริง
    // ฝังอยู่ในแม่แบบ — ไฟล์เวอร์ชันใหม่นี้ (เปลี่ยนชื่อ 2 ชีทสุดท้ายเป็น "สช.วิชาชีพ" และ
    // "สช.วิชาชีพ-ครู-นร.") ตรวจสอบแล้วเป็นแม่แบบว่างสะอาด ไม่มีปัญหาเดิมอีกต่อไป จึงรองรับครบทั้ง 5 ชีท
    '15_private_nonformal' => [
        'form_label'     => 'ตารางที่ 15 ข้อมูลโรงเรียนเอกชนนอกระบบ',
        'source_file'    => '15_ข้อมูลโรงเรียนเอกชนนอกระบบ.xlsx',
        'sheets' => [
            [
                // ตารางที่ 15.1 ข้อมูลโรงเรียนเอกชน ประเภทสอนศาสนาอิสลามอย่างเดียว
                'sheet_name'      => '15.1',
                'skip_rows'       => [1, 2],
                'header_rows'     => 4,
                'identity_cols'   => 10,
                'identity_fields' => ['school_code', 'school_name', 'address', 'tambon', 'amphoe',
                    'postal_code', 'phone', 'license_holder_name', 'manager_name', 'headteacher_name'],
                'value_type'      => 'numeric', // จำนวนผู้สอน/ผู้เรียน แยกตามเพศ
                'value_label'     => 'รายการ',
                'value_split_last' => 'เพศ',
            ],
            [
                // ตารางที่ 15.2 ข้อมูลสถาบันปอเนาะ
                'sheet_name'      => '15.2',
                'skip_rows'       => [1, 2],
                'header_rows'     => 4,
                'identity_cols'   => 10,
                'identity_fields' => ['seq_no', 'school_code', 'school_name', 'address', 'tambon', 'amphoe',
                    'postal_code', 'phone', 'tokkhru_name', 'pondok_size'],
                'value_type'      => 'numeric', // จำนวนโต๊ะครู/ผู้เรียน แยกตามเพศ
                'value_label'     => 'รายการ',
                'value_split_last' => 'เพศ',
            ],
            [
                // ตารางที่ 15.3 ข้อมูลศูนย์การศึกษาอิสลามประจำมัสยิด (ตาดีกา)
                'sheet_name'      => '15.3',
                'skip_rows'       => [1, 2],
                'header_rows'     => 4,
                'identity_cols'   => 10,
                'identity_fields' => ['seq_no', 'school_code', 'school_name', 'address', 'tambon', 'amphoe',
                    'postal_code', 'phone', 'admin_name', 'pondok_size'],
                'value_type'      => 'numeric', // จำนวนผู้สอน/ผู้เรียน แยกตามเพศ
                'value_label'     => 'รายการ',
                'value_split_last' => 'เพศ',
            ],
            [
                // หมายเหตุลำดับคอลัมน์: อำเภอมาก่อนตำบล (เหมือนมาตรฐานทั่วไป) แต่ชีทนี้ไม่มี
                // คอลัมน์ตัวเลขเลย (แค่ข้อมูลพื้นฐานของโรงเรียนแต่ละประเภท) — เหมือนฟอร์ม 12 ตรงที่
                // ทุกคอลัมน์เป็น identity ทั้งหมด ไม่มี value column เหลือ
                // ตารางที่ 15.4 ข้อมูลพื้นฐานโรงเรียนเอกชนนอกระบบ แต่ละประเภท
                'sheet_name'      => 'สช.วิชาชีพ',
                'skip_rows'       => [1, 2],
                'header_rows'     => 3,
                'identity_cols'   => 12,
                'identity_fields' => ['school_code', 'school_name', 'amphoe', 'tambon', 'school_type',
                    'address', 'postal_code', 'phone', 'established_date', 'license_holder_name',
                    'manager_name', 'director_name'],
                'value_type'      => 'text',
            ],
            [
                // ตารางที่ 15.5 จำนวนนักเรียน/ครู โรงเรียนเอกชนนอกระบบ จำแนกตามประเภท
                // ต่างจากชีทอื่นในฟอร์มนี้ตรงที่ไม่มีแถวว่างคั่นก่อนหัวตาราง (แถว 3 คือหัวตาราง
                // ระดับแรกเลย ไม่ใช่แถว 4) — ตรวจสอบจากไฟล์ข้อมูลจริงที่ผู้ใช้งานอัปโหลดแล้ว
                'sheet_name'      => 'สช.วิชาชีพ-ครู-นร.',
                'skip_rows'       => [1, 2],
                'header_rows'     => 4,
                'identity_cols'   => 5,
                'identity_fields' => ['school_code', 'school_name', 'amphoe', 'tambon', 'school_type'],
                'value_type'      => 'numeric', // จำนวนนักเรียน/ครู แยกชาย/หญิง/รวม — "รวม" เป็นผลรวม
                // ไม่ใช่มิติอิสระจริง จึงไม่ split_last (ดู "จุดที่พลาดมาก่อน" ใน ai_note.md)
                'value_label'     => 'รายการ',
            ],
        ],
    ],

];
