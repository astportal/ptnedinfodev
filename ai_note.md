# 🤖 AI Collaboration Note (`ai_note.md`)

เอกสารนี้จัดทำขึ้นเพื่อให้ AI และนักพัฒนาทำงานร่วมกันอย่างต่อเนื่องผ่านหลายเครื่องคอมพิวเตอร์

---

## 📌 1. ข้อมูลโดเมนและสภาพแวดล้อม (Environment Architecture)

* **Dev (สภาพแวดล้อมทดสอบ)**:
  * URL: `https://dev-ptnedinfo.timevela.com`
  * Git Branch: `develop`
  * Plesk Path: `/dev-ptnedinfo.timevela.com` (หรือ path ที่กำหนดบน Plesk)

* **Production (สภาพแวดล้อมใช้งานจริง)**:
  * URL: `https://ptnedinfo.timevela.com`
  * Git Branch: `main`
  * Plesk Path: `/httpdocs` หรือ `/ptnedinfo.timevela.com`

---

## ☁️ 2. ข้อมูลการเชื่อมต่อ Hosting & GitHub

* **Hosting Provider**: HostAtom (จัดการด้วย **Plesk Control Panel**)
* **GitHub Repository**: `https://github.com/astportal/ptnedinfodev.git`
* **GitHub Username**: `astportal` / `aungkiko`
* **Deployment Mechanism**: 
  * ใช้ **Plesk Git Extension** + **Webhook** ในการ Auto Pull โค้ดลง Server ทันทีที่มีการ Push

---

## 🔄 3. สายการพัฒนาและข้อตกลง Git Workflow

1. **การพัฒนาและทดสอบ**:
   * โค้ดฟีเจอร์ใหม่หรือการแก้ไข ให้ทำบน branch **`develop`**
   * คำสั่ง Push ไปยัง Dev:
     ```bash
     git checkout develop
     git add .
     git commit -m "feat/fix: อธิบายสิ่งที่แก้ไข"
     git push origin develop
     ```
   * ➔ Plesk จะดึงโค้ดไปแสดงผลที่ **`dev-ptnedinfo.timevela.com`** ทันที

2. **การนำขึ้นระบบจริง (Production)**:
   * เมื่อทดสอบระบบบน Dev ผ่านเรียบร้อยแล้ว ให้ทำการ Merge เข้า branch **`main`**:
     ```bash
     git checkout main
     git merge develop
     git push origin main
     ```
   * ➔ Plesk จะดึงโค้ดไปแสดงผลที่ **`ptnedinfo.timevela.com`** (เว็บจริง) ทันที

---

## 📝 4. บันทึกโครงสร้างเทคโนโลยี & โน้ตเพิ่มเติมสำหรับ AI

* **Tech Stack**: *(ระบุเพิ่มเติมได้ เช่น HTML/CSS/JS หรือ Framework ที่เลือกใช้)*
* **ข้อควรระวังเรื่องความปลอดภัย**: ห้าม Commit ไฟล์ที่มี API Keys หรือ Passwords ลงใน GitHub Repository โดยเด็ดขาด ให้ใช้ `.env` หรือสภาพแวดล้อมเฉพาะ

---

*อัปเดตล่าสุดเมื่อ: 2026-08-21*
