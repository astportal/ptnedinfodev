<?php
/**
 * Form registry — the ONLY place needed to add support for a new form/sheet.
 *
 * Each entry describes one sheet of one Excel template:
 *   form_key        : unique id used in the database and URLs, e.g. "1_agency"
 *   form_label      : human label shown in the UI
 *   sheet_name      : exact sheet name inside the .xlsx (must match the template)
 *   header_rows     : how many rows (from the top) are header rows (title + all header levels)
 *   title_row       : which of those rows is the title row to ignore when building column labels (0 = none)
 *   identity_cols   : how many leading columns are "identity" columns (ลำดับที่ / รหัสสถานศึกษา / ชื่อหน่วยงาน ฯลฯ)
 *                     — these become fixed fields on the submission row instead of generic value columns.
 *   identity_fields : field name for each identity column, in order (must match identity_cols count)
 *   value_type      : 'numeric' (default) or 'text'. When 'numeric', every value column is checked:
 *                      "-" is treated as 0, "/" is treated as 1, plain numbers pass through as-is,
 *                      and anything else is flagged as needs_review instead of being guessed (see
 *                      Importer::classifyValue). Use 'text' for sheets whose value columns are genuinely
 *                      free text / categorical (names, sizes, types) — no numeric inference is applied.
 *   value_level_labels : optional. Column headers are stored as one joined path (e.g.
 *                      "ข้าราชการ / ชาย"); the "tidy" export (for Excel PivotTable) splits that back
 *                      into separate level columns. This array names those level columns in order
 *                      (e.g. ['ประเภทบุคลากร', 'เพศ']). Levels beyond the array, or when omitted
 *                      entirely, are labeled "ระดับที่ N" automatically.
 *
 * Everything after identity_cols is stored generically as (column_path => value), where column_path
 * is the header text of every header row for that column, joined with " / ". This is what lets the
 * SAME importer support every double/triple-header report table without hand-mapping each column.
 *
 * To add a new form: inspect the template's header rows, add one array entry below, done.
 */

return [

    '1_agency' => [
        'form_label'     => 'ตารางที่ 1 ข้อมูลพื้นฐานหน่วยงานต้นสังกัดสถานศึกษา',
        'source_file'    => '1_ข้อมูลพื้นฐานหน่วยงานต้นสังกัดสถานศึกษา.xlsx',
        'sheets' => [
            [
                'sheet_name'      => 'ข้อมูลพื้นฐานหน่วยงาน',
                'title_row'       => 1,
                'header_rows'     => 4,
                'identity_cols'   => 3,
                'identity_fields' => ['agency_name', 'admin_name', 'phone'],
                'value_type'      => 'numeric', // จำนวนบุคลากรแยกชาย/หญิง
                'value_level_labels' => ['หมวด', 'ประเภทบุคลากร', 'เพศ'],
            ],
        ],
    ],

    '2_school_basic' => [
        'form_label'     => 'ตารางที่ 2 ข้อมูลพื้นฐานสถานศึกษา',
        'source_file'    => '2_ข้อมูลพื้นฐานสถานศึกษา.xlsx',
        'sheets' => [
            [
                'sheet_name'      => 'ข้อมูลสถานศึกษา',
                'title_row'       => 1,
                'header_rows'     => 3,
                'identity_cols'   => 6,
                'identity_fields' => ['seq_no', 'school_code', 'agency_name', 'school_name', 'amphoe', 'tambon'],
                'value_type'      => 'text', // ชื่อผู้บริหาร/โทรศัพท์/ขนาด/ประเภท เป็นข้อความ ไม่ใช่ตัวเลข
            ],
            [
                'sheet_name'      => 'ระดับที่เปิดสอน',
                'title_row'       => 1,
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => ['seq_no', 'school_code', 'agency_name', 'school_name', 'amphoe', 'tambon'],
                'value_type'      => 'numeric', // ติ๊ก "/" = เปิดสอน, "-" = ไม่เปิดสอน ในแต่ละระดับชั้น
                'value_level_labels' => ['หมวด', 'ระดับชั้น'],
            ],
        ],
    ],

    '3_classrooms' => [
        'form_label'     => 'ตารางที่ 3 จำนวนห้องเรียน',
        'source_file'    => '3_จำนวนห้องเรียน.xlsx',
        'sheets' => [
            [
                'sheet_name'      => '3.จำนวนห้องเรียน',
                'title_row'       => 1,
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => ['seq_no', 'school_code', 'agency_name', 'school_name', 'amphoe', 'tambon'],
                'value_type'      => 'numeric', // จำนวนห้องเรียนแยกตามระดับชั้น
                'value_level_labels' => ['ระดับการศึกษา', 'กลุ่มย่อย', 'ชั้นปี'],
            ],
        ],
    ],

];
