# KosanHub – Platform Pengelolaan Kos-kosan

**Mata Kuliah:** Komputasi Awan 2026–2027  
**Studi Kasus:** K12 – KosanHub  
**Stack:** Laravel 12 + MySQL + Docker + Jenkins

## Anggota
- Mikhael Jeremiah Marjorie Tampa (Tech Lead / Backend)
- Renanthera Areno Zulkarnain (DevOps Engineer)
- Mas Moh Abdulloh Al Wahabi (Container Engineer)

## Fitur Wajib
- CRUD Property & Room
- Data Tenant & Contract
- Pembuatan Tagihan Bulanan (otomatis via Worker)
- Pencatatan Pembayaran
- Daftar Tunggakan

## Aturan Bisnis
1. Satu kamar hanya boleh memiliki **satu kontrak aktif**.
2. Tagihan bulan pertama dihitung **proporsional** berdasarkan tanggal masuk.
3. Denda keterlambatan **2% per minggu**, maksimal **10%**.
4. Tagihan bulanan **tidak boleh terbit dua kali** untuk periode yang sama.

## Tantangan DevOps
Container **worker** terpisah yang memproses job pembuatan tagihan dari queue (database queue). Smoke Test memicu pembuatan tagihan dan memastikan worker memprosesnya.