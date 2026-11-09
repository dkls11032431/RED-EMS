# Energy monitoring (XAMPP)

PHP site for the SPZ energy monitor. The pages live in `energy_monitor`. Gateways post meter packets to `datacollector/getdata.php`.

## Run it in XAMPP

1. Copy `energy_monitor` and `datacollector` into the XAMPP web root (`C:\xampp\htdocs` on Windows).
2. Start Apache and MySQL from the XAMPP control panel.
3. The site uses MySQL database `princek2` on `127.0.0.1:3306`, user `root`, empty password. That is the XAMPP default, set in `energy_monitor/config/db.php`.
4. Open `http://localhost/energy_monitor/login.php`.

Replace `C:\xampp\htdocs\datacollector\getdata.php` and add `gateway_request.php` beside it. Leave the old `raw_capture.log` in place so new lines append.

## What broke on 5 Oct 2026

Until 17:58 that day, `datacollector/raw_capture.log` shows a packet about once a minute with `db`, `siteno`, and a 230-character hex `data` field. From 17:59 onward every line has empty fields, on the same schedule.

`getdata.php` used to read only `php://input`. That works when the body is `db=...&siteno=...&data=...` and PHP does not parse it into `$_POST` (these gateways often send no Content-Type). It misses the packet when the same fields arrive as form fields or on the query string, and the old log did not keep the raw body, so an empty line could not show which of those happened.

The updated script checks form fields, the query string, and the raw body. Each log line now includes the HTTP method, content type, raw body length, query string, and the first 180 characters of the body.

After you copy the files in, wait for one gateway cycle and open `datacollector/raw_capture.log`. A stored packet looks like `data_len=230` and `stored=` greater than 0. The script answers `OK (N readings stored, type A)` or `type B`.

The dashboard reads `meter_readings`, not the capture log. `getdata.php` decodes each push and inserts those rows. Block `000D` supplies kW, frequency, and line voltage. Block `0BB8` supplies `EB-KWH` (register 3002) and `DG-KWH` (register 3000). Phase voltage and current are not in the push, so `Vr` / `Vy` / `Vb` are the line voltages divided by √3, and `Ir` / `Iy` / `Ib` are calculated from the phase kW.

`datacollector/site_meters.php` maps a gateway site to a location. `0923DL0035` is Pune: live power and EB energy go to meter 1, and diesel kWh goes to a meter named `Pune DG`. `0723DL0011` is Trichy and has no DG: its readings go to a meter named `Trichy Main`. UPS is not in these packets.

Copy these into `C:\xampp\htdocs` over the existing files:

- `datacollector/getdata.php`
- `datacollector/decode_frame.php`
- `datacollector/gateway_request.php`
- `datacollector/site_meters.php`
- `energy_monitor/dashboard.php`
- `energy_monitor/api/getLiveData.php`
- `energy_monitor/api/kwdata.php`
- `energy_monitor/api/voltagedata.php`
- `energy_monitor/api/currentdata.php`

## USR module (firmware V4104, Httpd Client)

The module in the screenshot is set to POST `/datacollector/getdata.php?`. On this firmware a URL that ends with `?` is the GET form: serial bytes are attached to the path, and the POST body is left empty. `getdata.php` then stores nothing.

Set the serial page like this, click Save, then Reboot:

| Field | Value |
| --- | --- |
| Work Mode | Httpd Client |
| Httpd Chose | POST |
| Remote Server Addr | `10.5.2.51` (the PC running XAMPP) |
| Remote Port Number | The Apache port. XAMPP’s default is `80`. Keep `8080` only if Apache is really listening on 8080. |
| Httpd URL | `/datacollector/getdata.php` |
| Httpd Header | `User-Agent: Mozilla/4.0` and `Content-Type: application/x-www-form-urlencoded` |
| Remove Httpd Head | checked |
| Similar RFC2217 | unchecked |

Do not put `Connection: close` in the header. In POST mode the module already adds `Connection` and `Content-Length`. The header name is `User-Agent` with a hyphen.

## EB and DG worksheets

EB Bills and DG Bills open a BECIL-style worksheet for one branch and one month. South states (Andhra Pradesh, Karnataka, Kerala, Tamil Nadu) are KAL. Every other state on the location sheet is SAFL. The branch list and the broadcaster/transmitter list are loaded the first time a bill page is opened.

The opening reading of each meter, including the common-area meter, is the previous month’s new reading and cannot be typed over. The first month at a branch is entered by hand. Units are new minus old. Common-area units are split across the broadcasters at that branch so the shares add back to the common meter. Proportionate amounts add back to the bill. GST is 18% of the management charge, and the invoice column is that management charge plus GST.

Bill comparison, under Reports, sets one branch against another, one broadcaster against another, the broadcasters inside a single branch, and transmitter against transmitter. It also shows the latest bill against that branch’s own average.

## RED SURYAN EMS

The same PHP application now uses the RED SURYAN shell: navigation, light and dark display, and the existing pages. New pages read the same database. They do not invent live readings.

| Page | What it uses |
| --- | --- |
| `dashboard.php` | One button per billing location. Green is a fresh EB sample, orange is DG, red is no sample in the last five minutes |
| `dg_analytics.php` | CTI and Office diesel totals compared across locations |
| `electrical.php` | Latest EB, DG, solar, voltage, current and power-factor samples, plus daily, monthly and yearly register movement |
| `equipment.php` | Meters whose names identify UPS, DG, air conditioning, transformer or solar |
| `devices.php` | Device registry in `iot_devices`, plus the age of the last stored sample |
| `dg_operations.php` | Operator-entered CTI and Office diesel log in `dg_daily_log`, with Excel and PDF export |

South states remain KAL and the other states remain SAFL. BECIL and NON BECIL worksheets are unchanged. Broadcaster names for fuel sharing come from `bill_site_broadcasters`, which is filled from the catalog in `includes/bill_catalog.php`. `Broadcaster details.xlsx` is not in this repository.

MQTT is not implemented. Gateways still post to `datacollector/getdata.php`. `modbus_poller.php` still speaks Modbus TCP to the USR gateway.

### Environment

These variables are optional. When they are unset, the application keeps the previous XAMPP defaults.

| Variable | Default |
| --- | --- |
| `EMS_DB_HOST` | `127.0.0.1` |
| `EMS_DB_PORT` | `3306` |
| `EMS_DB_NAME` | `princek2` |
| `EMS_DB_USER` | `root` |
| `EMS_DB_PASS` | empty |
| `MODBUS_HOST` | `172.16.11.102` |
| `MODBUS_PORT` | `502` |
| `MODBUS_METER_ID` | `1` |

Copy `energy_monitor` and `datacollector` into the web root, start Apache and MySQL, then open `energy_monitor/login.php`. The first successful plant page creates `dg_daily_log` and `iot_devices` if they are missing. Existing tables are not rebuilt.
