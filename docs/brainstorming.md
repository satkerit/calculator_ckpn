# Resume & Brainstorming Konsep CKPN BPRS (PSAK 414 & PAPSI BPRS)

---

## Bagian 1: Resume Klarifikasi Pemahaman & Snapshot PD Net Flow (Musyarakah & IMBT)

### 1. Evaluasi Pemahaman Awal

| Aspek | Status Pemahaman | Penjelasan & Koreksi |
| :--- | :--- | :--- |
| **CKPN Musyarakah saat Jatuh Tempo** | **BENAR** | Porsi pembiayaan berjalan menggunakan pendekatan PPKA (PSAK 459 / POJK Kualitas Aset). CKPN berbasis PSAK 414 dihitung saat terjadi tunggakan angsuran pokok atau telah jatuh tempo (beralih menjadi Piutang Pokok/Bagi Hasil Musyarakah Jatuh Tempo). |
| **CKPN IMBT saat Terjadi Tunggakan** | **BENAR** | Diukur atas piutang ijarah/ujrah ketika terdapat bukti objektif penurunan nilai (seperti tunggakan angsuran ujrah/sewa). |
| **Komponen EAD** | **KURANG TEPAT** | **EAD = Entire Outstanding Pokok (Baki Debet)**, bukan sekadar nominal tunggakan pokok. |

---

### 2. Konsep Dasar EAD (Exposure at Default)
* **Seluruh Baki Debet / Outstanding Pokok:** Saat suatu fasilitas pembiayaan mengalami penurunan nilai (menunggak), seluruh sisa tagihan pokok terutang terekspos risiko kerugian (*loss exposure*).
* **Cross-Default per Rekening:** Ketika angsuran menunggak (misal 15 hari), **seluruh saldo outstanding pokok** rekening tersebut berpindah klasifikasi ke *bucket* hari tunggakan yang sesuai.

---

### 3. Kriteria Snapshot Data & Populasi PD Net Flow (Musyarakah & IMBT)

1. **Penggunaan Data Populasi:**
   * **Seluruh rekening** pada akad Musyarakah dan IMBT (baik yang lancar/belum jatuh tempo maupun yang menunggak) **wajib diikutsertakan secara penuh** dalam *snapshot* data bulanan.
2. **Peran Pembiayaan Lancar / Belum Jatuh Tempo:**
   * Ditempatkan pada **Bucket 1 (0 Hari Tunggakan)**.
   * Berfungsi sebagai dasar pembagi (*denominator*) untuk mengukur persentase perpindahan (*flow rate*) dari kategori lancar menuju *bucket* tunggakan hingga *default* (>360 hari / hapus buku).
3. **Pengelompokan Bucket per Akad:**
   * **Musyarakah Lancar / Belum Jatuh Tempo:** Masuk Bucket 1 (0 Hari).
   * **Musyarakah Menunggak / Jatuh Tempo:** Masuk Bucket 2, 3, dst. sesuai hari tunggakan.
   * **IMBT Lancar:** Masuk Bucket 1 (0 Hari).
   * **IMBT Menunggak Ujrah/Sewa:** Masuk Bucket 2, 3, dst. sesuai hari tunggakan.
   * **Nilai EAD yang Dimasukkan ke Bucket:** Selalu memakai **Outstanding Pokok (Baki Debet)**.

---

## Bagian 2: Penjelasan & Ketentuan CKPN untuk Akad Murabahah dan Multijasa

### 1. Karakteristik Dasar & Komponen Piutang
* **Ruang Lingkup:** Masuk penuh dalam cakupan **PSAK 414** (Aset Keuangan Syariah).
* **Dasar Pengukuran Piutang (EAD):**
  $$\text{Piutang Netto} = \text{Piutang Murabahah/Multijasa Bruto} - \text{Marjin/Ujrah Ditangguhkan}$$
  *Catatan:* Hanya **pokok piutang** yang dihitung CKPN-nya. Marjin/ujrah ditangguhkan diekspos sebagai pengurang piutang bruto, bukan sebagai komponen CKPN.

---

### 2. Skema Evaluasi Penurunan Nilai (Individual vs Kolektif)

#### A. Evaluasi Individual (Signifikan)
* **Kriteria:** Diperuntukkan bagi fasilitas/debitur dengan plafon/saldo di atas ambang batas signifikansi BPRS (misal: $\ge \text{Rp1 Miliar}$) yang memiliki bukti objektif penurunan nilai (tunggakan, restrukturisasi, kebangkrutan).
* **Metode Perhitungan:** *Collateral Shortfall*
  $$\text{CKPN} = \text{Nilai Tercatat Pokok} - (\text{Nilai Likuidasi Agunan} - \text{Biaya Pelepasan})$$
* **Ketentuan Agunan:** Jika nilai likuidasi agunan bersih lebih besar atau sama dengan nilai tercatat piutang, maka $\text{CKPN} = 0$.

#### B. Evaluasi Kolektif (Tidak Signifikan)
* **Kriteria:** Pembiayaan Murabahah dan Multijasa di bawah ambang batas signifikansi, atau yang dinilai secara kelompok/kolektif.
* **Formula Utama:**
  $$\text{CKPN} = \text{PD} \times \text{LGD} \times \text{EAD}$$
  * **PD (Probability of Default):** Dihitung menggunakan metode **Net Flow (Roll Rates)** atau **Migration Analysis** dengan data histori bulanan minimal 36 bulan (3 tahun).
  * **LGD (Loss Given Default):** Menggunakan *Recovery Rate* historis dari pembiayaan yang dihapusbuku atau rata-rata persentase selisih eksekusi agunan (*Collateral Shortfall*).
  * **EAD (Exposure at Default):** Sisa baki debet pokok Murabahah/Multijasa pada tanggal laporan.

---

### 3. Penerapan Snapshot PD Net Flow pada Murabahah & Multijasa

* **Proses Snapshot Bulanan:** Setiap akhir bulan, seluruh rekening aktif Murabahah dan Multijasa dipetakan ke dalam 14 *bucket* hari tunggakan berdasarkan hari keterlambatan angsuran pokok/marjin.
* **Pergerakan Saldo:** Lacak perpindahan baki debet pokok dari Bucket 1 (0 Hari) ke Bucket 2 (1–30 Hari), hingga berakhir di Bucket 14 (>360 Hari / *Default*).
* **Rasio Kerugian (Flow to Loss):** Persentase akhir dari akumulasi perpindahan tersebut digunakan sebagai faktor angka PD Kolektif Murabahah dan Multijasa.

---

## Bagian 3: Klarifikasi & Keputusan Implementasi (Konfirmasi Owner, 10 Oktober 2026)

Keputusan final berdasarkan kondisi data yang tersedia saat ini:

### K1. EAD Murabahah & Multijasa (resolusi G1)
* **EAD = Outstanding pembiayaan** (`outstanding_balance`), **tanpa** pengurangan marjin/ujrah ditangguhkan.
* Alasan: nilai outstanding pada data sumber **sudah netto** (tidak termasuk marjin di dalamnya).

### K2. EAD & Snapshot Musyarakah & IMBT (resolusi G3)
* **Snapshot (PD Net Flow):** selalu memakai **Outstanding Pokok** — berlaku untuk seluruh akad (Murabahah, Multijasa, Musyarakah, IMBT), termasuk rekening lancar (Bucket 1).
* **CKPN:**
  * **IMBT:** EAD = **tunggakan pokok** (`principal_arrears`); CKPN hanya dihitung jika **menunggak**. Rekening tidak menunggak tetap masuk snapshot (Bucket 1) namun EAD = 0.
  * **Musyarakah:** CKPN hanya dihitung jika **sudah jatuh tempo**; snapshot tetap memakai outstanding pokok.

### K3. Klasifikasi Individual vs Kolektif (resolusi G4)
* **Tidak menggunakan ambang batas nominal** (ambang signifikansi ≥ Rp 1 Miliar tidak diterapkan).
* Kriteria INDIVIDUAL: nasabah NPL (rating 3, 4, 5) dengan outstanding terbesar (**TOP N**).

### K4. Window Historis PD Net Flow (resolusi G7)
* Window snapshot historis **dinamis** (configurable per periode), namun secara **default wajib 36 bulan**.

### K5. Basis Snapshot untuk Seluruh Akad
* Untuk kebutuhan snapshot (Tabel 1/2/3 PD Net Flow) gunakan **outstanding pokok** dan berlaku pada **seluruh akad** saat perhitungan CKPN.