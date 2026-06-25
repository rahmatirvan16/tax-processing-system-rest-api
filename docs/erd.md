# ERD - Tax Processing System (TPS)

Diagram relasi antar entitas (render otomatis di GitHub via Mermaid).
Versi `.sql` lengkap tersedia di [`docs/erd.sql`](erd.sql).

```mermaid
erDiagram
    USERS ||--o{ PEMBAYARAN : "mencatat (dicatat_oleh)"
    USERS }o--o| WAJIB_PAJAK : "tertaut (role WAJIB_PAJAK)"
    USERS ||--o{ ACTIVITY_LOG : "pelaku (causer)"
    WAJIB_PAJAK ||--o{ KEWAJIBAN_PAJAK : "memiliki"
    KEWAJIBAN_PAJAK ||--o{ PEMBAYARAN : "dibayar via"
    PEMBAYARAN ||--|| DENDA : "menghasilkan"

    USERS {
        bigint id PK
        string name
        string username UK
        string email UK "nullable"
        string password "bcrypt"
        enum role "ADMIN|PETUGAS|WAJIB_PAJAK"
        bigint wajib_pajak_id FK "nullable, hanya WAJIB_PAJAK"
    }
    WAJIB_PAJAK {
        bigint id PK
        enum jenis "INDIVIDU|BADAN"
        string nama
        string nik UK "16 digit"
        string npwp UK
        string nib UK
        boolean status_aktif
    }
    KEWAJIBAN_PAJAK {
        bigint id PK
        bigint wajib_pajak_id FK
        string jenis_pajak
        string masa_pajak
        decimal pokok_pajak
        date jatuh_tempo
        enum status "BELUM_LUNAS|LUNAS|LEBIH_BAYAR"
    }
    PEMBAYARAN {
        bigint id PK
        bigint kewajiban_pajak_id FK
        decimal nominal
        date tanggal_bayar
        enum status "LUNAS|KURANG_BAYAR|LEBIH_BAYAR"
        bigint dicatat_oleh FK "nullable"
    }
    DENDA {
        bigint id PK
        bigint pembayaran_id FK
        decimal denda_telat "2% x pokok"
        decimal denda_kurang "1% x selisih"
        decimal total_denda
        boolean is_telat
        boolean is_kurang_bayar
    }
    ACTIVITY_LOG {
        bigint id PK
        string log_name
        string description
        string subject_type
        bigint subject_id
        string causer_type
        bigint causer_id
        json properties
    }
```

## Catatan relasi

- **users.wajib_pajak_id** → menautkan akun login role `WAJIB_PAJAK` ke datanya sendiri
  (NULL untuk ADMIN/PETUGAS). Inilah dasar fitur "wajib pajak hanya melihat data sendiri".
- **wajib_pajak → kewajiban_pajak → pembayaran → denda**: rantai one-to-many,
  cascade-delete ke bawah; `denda` 1-1 dengan `pembayaran` (dihitung otomatis).
- **activity_log**: audit trail otomatis (Spatie) untuk setiap perubahan data.
