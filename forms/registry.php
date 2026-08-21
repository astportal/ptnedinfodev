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

];
