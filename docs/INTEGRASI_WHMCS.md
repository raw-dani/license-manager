# Integrasi WHMCS (Billing Bridge)

## Ringkasan

License Manager menyediakan **bridge untuk integrasi WHMCS** yang dapat digunakan untuk penagihan (billing). Fungsionalitas ini **tidak aktif secara default** dan dapat diaktifkan kapan saja dengan mengisi kredensial WHMCS API.

## Arsitektur

```
┌─────────────────────────┐        ┌──────────────────────────┐
│   License Manager       │        │        WHMCS            │
│   (Laravel)             │        │  (Billing/Penagihan)    │
│                         │        │                          │
│ ┌─────────────────────┐ │  API   │ ┌──────────────────────┐ │
│ │ WhmcsApiClient      │ │◄──────►│ │ includes/api.php     │ │
│ │ - syncLicense       │ │        │ │ - AddProduct         │ │
│ │ - checkInvoice      │ │        │ │ - GetInvoice         │ │
│ │ - pushUsage         │ │        │ │ - UpdateClientProduct│ │
│ └─────────────────────┘ │        │ └──────────────────────┘ │
│                         │        │                          │
│ ┌─────────────────────┐ │ Webhook│ ┌──────────────────────┐ │
│ │ WhmcsBillingService │ │◄───────│ │ Webhook Callback     │ │
│ │ - handleWebhook     │ │        │ │ - InvoicePaid        │ │
│ │ - activateByInvoice │ │        │ │ - InvoiceUnpaid      │ │
│ └─────────────────────┘ │        │ └──────────────────────┘ │
└─────────────────────────┘        └──────────────────────────┘
```

## Configuration

### 1. Aktifkan WHMCS Integration

Buka **Admin Panel → Settings → WHMCS Billing Bridge** dan isi:

| Field | Deskripsi |
|---|---|
| Enable WHMCS Integration | Centang untuk mengaktifkan |
| WHMCS URL | URL instalasi WHMCS Anda (misal `https://whmcs.example.com`) |
| API Identifier | WHMCS API identifier (dari Configuration → Manage API Credentials) |
| API Secret | WHMCS API secret |

### 2. Buat API Credential di WHMCS

1. Login ke WHMCS admin
2. Buka **Setup → General Settings → API**
3. Create credentials
4. Salin `API Identifier` dan `API Secret`
5. Catat `API Secret` untuk dipaste ke License Manager

## Webhook Configuration

Untuk menerima event dari WHMCS:

1. Buka **WHMCS → Setup → General Settings → Webhooks**
2. Tambah webhook ke endpoint:
   ```
   https://your-license-server.com/api/whmcs/webhook
   ```
3. Pilih event yang ingin diterima:
   - `InvoicePaid` - lisensi diaktifkan saat invoice dibayar
   - `InvoiceUnpaid` - lisensi di-suspend saat invoice belum dibayar

## Alur Kerja

### Alur Pembayaran (Invoice Paid)
1. Pelanggan membayar invoice di WHMCS
2. WHMCS mengirim webhook `InvoicePaid`
3. License Manager mencari lisensi dengan `metadata.whmcs_invoice_id` atau `metadata.whmcs_order_id` yang cocok
4. Status lisensi diubah menjadi `active`
5. Aktivitas dicatat di `activation_logs`

### Alur Belum Bayar (Invoice Unpaid)
1. Invoice WHMCS belum dibayar/batas waktu lewat
2. WHMCS mengirim webhook `InvoiceUnpaid`
3. License Manager me-suspend lisensi terkait
4. Status lisensi menjadi `suspended`

### Sinkronisasi Manual
- `syncLicenseToWhmcs()` - dipanggil saat lisensi dibuat untuk mendaftarkannya sebagai produk di WHMCS
- `checkInvoiceStatus()` - memeriksa status invoice secara manual
- `pushUsageToWhmcs()` - mengirim data penggunaan/aktivasi ke WHMCS

## Metadata License

Saat membuat lisensi yang terhubung ke WHMCS, isi kolom `metadata`:

```json
{
  "whmcs_client_id": 123,
  "whmcs_service_id": 456,
  "whmcs_order_id": 789,
  "whmcs_invoice_id": 1011
}
```

## Log Sinkronisasi

Semua aktivitas sinkronisasi tercatat di tabel `whmcs_sync_logs`:

| Field | Deskripsi |
|---|---|
| `action` | `sync_license`, `check_invoice`, `push_usage` |
| `status` | `success`, `failed`, `pending` |
| `request_data` | Data yang dikirim ke WHMCS |
| `response_data` | Respons dari WHMCS |
| `error` | Pesan error jika gagal |

## Keamanan

- Webhook endpoint sebaiknya dilindungi dengan validasi signature untuk mencegah request palsu
- API credentials disimpan terenkripsi
- Semua request ke WHMCS menggunakan HTTPS