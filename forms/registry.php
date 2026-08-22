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

    '10_teachers' => [
        'form_label'     => 'ตารางที่ 10 ข้อมูลครู',
        'source_file'    => '10_ข้อมูลครู.xlsx',
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
                'sheet_name'      => '10.3ทุกสังกัด(ยกเว้นสาธิต&อุดม)',
                // แถว 4 ซ้ำคำว่า "วุฒิการศึกษาที่สำเร็จสูงสุด" ทุกคอลัมน์ ไม่ช่วยแยกแยะ
                'skip_rows'       => [1, 2, 4],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้สอนแยกตามวิทยฐานะ วุฒิการศึกษา และเพศ
                'value_label'     => 'วิทยฐานะ/วุฒิ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '10.3กรอกเฉพาะสาธิต&อุดมฯ',
                'skip_rows'       => [1, 2, 4],
                'header_rows'     => 6,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้สอนแยกตามตำแหน่งวิชาการ วุฒิการศึกษา และเพศ
                'value_label'     => 'ตำแหน่ง/วุฒิ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '10.4ทุกสังกัด',
                // แถว 3 ซ้ำคำว่า "จำนวนผู้สอนแยกตามวิชาเอก" ทุกคอลัมน์ ไม่ช่วยแยกแยะ
                'skip_rows'       => [1, 2, 3],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => $stdIdentity,
                'value_type'      => 'numeric', // จำนวนผู้สอนแยกตามวิชาเอก (ไม่มีแยกเพศ)
                'value_label'     => 'วิชาเอก',
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
        'sheets' => array_map(
            fn($sheetName) => [
                'sheet_name'      => $sheetName,
                'skip_rows'       => [1],
                'header_rows'     => 2,
                'identity_cols'   => 6,
                'identity_fields' => ['seq_no', 'school_code', 'agency_name', 'school_name', 'tambon', 'amphoe'],
                'value_type'      => 'text',
            ],
            [
                '1. พระราชดำริ', '2. โครงการอาหารเช้า', '3.ประชารัฐ', '4.พักนอน', '5.รร กองทุน',
                '6.รร.ราชประชานุเคราะห์', '7. ขยายโอกาส', '8. ในฝัน', '9.ดีตำบล', '10.พัฒนาคุณภาพ',
                '11.มาตรฐานสากล', '12.สเต็ม', '13. icu',
            ]
        ),
    ],

    // หมายเหตุ: ไม่รองรับไฟล์ 15_ข้อมูลโรงเรียนเอกชนนอกระบบ.xlsx ตามคำขอของผู้ใช้งาน

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

    // รวมฟอร์ม 14ก และ 14ข เป็นชุดข้อมูลเดียว (นับ/ดู/ส่งออกรวมกัน) ตามคำขอผู้ใช้งาน — ทั้งสอง
    // ไฟล์มีโครงสร้างคอลัมน์เหมือนกันทุกประการ ต่างกันแค่กลุ่มศูนย์ที่สำรวจ จึงให้ทั้งสองชีท
    // (คนละไฟล์ .xlsx ต้นฉบับ) เขียนลง db_sheet_name เดียวกัน โดยแปะ 'center_source' ไว้ใน
    // extra_identity ของแต่ละแถว เพื่อให้ยังกรอง/แยกดูตามที่มาเดิมได้ถ้าต้องการ
    '14_childcare_centers' => [
        'form_label'     => 'ตารางที่ 14 ข้อมูลศูนย์พัฒนาเด็กเล็ก/สถานพัฒนาเด็กปฐมวัย (รวม 14ก+14ข)',
        'sheets' => [
            [
                'sheet_name'      => '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก',
                'source_file'     => '14_ก-ข้อมูลศูนย์พัฒนาเด็กเล็ก.xlsx',
                'db_sheet_name'   => '14.ศูนย์พัฒนาเด็กเล็ก+ปฐมวัย (รวม)',
                'optional'        => true, // อัปโหลดไฟล์นี้หรือ 14ข ไฟล์ใดไฟล์หนึ่งก็ได้ ไม่ใช่ทั้งสองพร้อมกัน
                'skip_rows'       => [1],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                // ลำดับคอลัมน์ระบุตัวตนของฟอร์มนี้ไม่เหมือนมาตรฐาน (ตำบลมาก่อนอำเภอ และมีคอลัมน์
                // "เป็นศูนย์ถ่ายโอน/ตั้งเอง" แทรก) — center_type ไม่ใช่ field มาตรฐาน จะถูกเก็บลง
                // extra_identity (JSON) แทนที่จะมีคอลัมน์ของตัวเองในตาราง
                'identity_fields' => ['seq_no', 'tambon', 'amphoe', 'agency_name', 'center_type', 'school_name'],
                'fixed_extra_identity' => ['center_source' => '14ก ศูนย์พัฒนาเด็กเล็ก'],
                'value_type'      => 'numeric', // จำนวนเด็กเล็กแยกอายุ/เพศ + จำนวนครูแยกวุฒิ/เพศ
                'value_label'     => 'รายการ',
                'value_split_last' => 'เพศ',
            ],
            [
                'sheet_name'      => '14.สถานพัฒนาเด็กปฐมวัย',
                'source_file'     => '14_ข-ข้อมูลสถานพัฒนาเด็กปฐมวัย.xlsx',
                'db_sheet_name'   => '14.ศูนย์พัฒนาเด็กเล็ก+ปฐมวัย (รวม)',
                'optional'        => true,
                'skip_rows'       => [1],
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => ['seq_no', 'tambon', 'amphoe', 'agency_name', 'center_type', 'school_name'],
                'fixed_extra_identity' => ['center_source' => '14ข สถานพัฒนาเด็กปฐมวัย'],
                'value_type'      => 'numeric',
                'value_label'     => 'รายการ',
                'value_split_last' => 'เพศ',
            ],
        ],
    ],

];
