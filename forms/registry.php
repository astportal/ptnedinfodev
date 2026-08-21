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
 *   value_label     : optional. The "tidy" export (for Excel PivotTable) turns every header level
 *                      of a column into one combined, readable label (e.g. "ประถมศึกษาปีที่ 1") in a
 *                      single column — this names that column (e.g. "ชั้นปี"). Defaults to "รายการ".
 *   value_split_last : optional. Pulls the last header level out into its own column with this
 *                      name (e.g. "เพศ") instead of joining it into value_label — use when the last
 *                      level is a genuinely independent dimension (ชาย/หญิง) that reads better on
 *                      its own than glued onto the category name.
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
                'title_row'       => 1,
                'header_rows'     => 4,
                'identity_cols'   => 6,
                'identity_fields' => ['seq_no', 'school_code', 'agency_name', 'school_name', 'amphoe', 'tambon'],
                'value_type'      => 'numeric', // จำนวนห้องเรียนแยกตามระดับชั้น
                'value_label'     => 'ชั้นปี', // เช่น "ประถมศึกษาปีที่ 1"
            ],
        ],
    ],

];
