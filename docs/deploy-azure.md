# Putting MCU-NEX online (Azure for Students + GitHub Actions)

When it's done, the site is online all the time, and **every push to `main` on GitHub updates it
by itself**.

You do the sign-ups and clicks yourself; this guide gives every step. The files it uses are in
`deploy/` and `.github/workflows/deploy.yml`.

**Time:** about 1–2 hours the first time.

**Cost:** free, with **no credit card**. Azure for Students gives you:
- **US$100 credit**, good for **12 months**. You can **renew it every year** while you're still a
  student.
- **750 hours a month of a small Linux server** (B1s or B2ats v2). 750 hours is one server running
  all month, so **only ever make one server**.

Small extras like the server's disk and its public IP address may take a few dollars a month from
the $100. That's fine: $100 lasts the whole year. If the credit ever runs out, Azure emails you
and **turns the site off** (it never charges you without asking). Free-tier rules change, so check
https://azure.microsoft.com/free/students when you sign up.

---

## 1. Azure for Students account

1. Go to **https://azure.microsoft.com/free/students** → **Start free**.
2. Sign in with a Microsoft account (or make one), then verify with your **school email**
   (e.g. `@mcu.edu.ph`). It may also send a code to your phone.
3. When it's done you land on the **Azure portal** (https://portal.azure.com).

## 2. Create the server

In the portal: search **Virtual machines** → **Create → Azure virtual machine**.

**Basics** tab:

| Setting | Choose |
|---|---|
| Subscription | **Azure for Students** |
| Resource group | **Create new** → `mcu-nex` |
| Virtual machine name | `mcu-nex` |
| Region | **(Asia Pacific) East Asia** (Hong Kong, close to PH). Student accounts often can't use the free sizes in Southeast Asia; in *See all sizes*, **Find deployable options** next to a size lists the regions where it works. |
| Availability options | No infrastructure redundancy required |
| Security type | Standard |
| Image | **Ubuntu Server 24.04 LTS – x64 Gen2** |
| Size | **Standard_B2ats_v2** (2 vCPU, 1 GB). The ~$9.56/month price shown is the normal price; the free hours cover it. Look for the *free services eligible* label. If it's not there, use **Standard_B1s**. *Not* the B2pts (that one is ARM). |
| Authentication type | **SSH public key** |
| Username | `azureuser` |
| SSH public key source | **Generate new key pair**, key pair name `mcu-nex_key` |
| Public inbound ports | **Allow selected ports** → tick **HTTP (80)**, **HTTPS (443)**, **SSH (22)** |

Leave **Run with Azure Spot discount** unticked.

**Disks** tab: OS disk type **Standard SSD**. Leave the size as it is.

**Management** tab: if you see **Enable auto-shutdown**, make sure it's **off** (otherwise the
site goes offline every night).

Click **Review + create → Create**. A box pops up: **Download private key and create resource**.
It saves `mcu-nex_key.pem` to your Downloads. **Keep that file safe; it's the only way into the
server.** Move it somewhere like `C:\Users\Jedediah\.ssh\mcu-nex_key.pem`.

Wait for "Your deployment is complete" → **Go to resource**.

## 3. Give it a free web address

HTTPS needs a name, not just an IP address. Azure gives you one for free.

1. On the server's **Overview** page, next to **DNS name**, click **Not configured**.
2. **Assignment: Static** (so the IP never changes).
3. **DNS name label:** `mcu-nex` (if taken, try `mcu-nex-thesis` or similar).
4. **Save.**

Your address is now something like **`mcu-nex.eastasia.cloudapp.azure.com`**. Below it
says the full name; copy it. Also copy the **Public IP address** from the Overview page.

In the steps below, replace:
- `YOUR-ADDRESS` with that full name
- `YOUR.PUBLIC.IP` with the IP

## 4. Log in to the server

On your PC (PowerShell):

```powershell
icacls "C:\Users\Jedediah\.ssh\mcu-nex_key.pem" /inheritance:r /grant:r "$($env:USERNAME):R"
ssh -i "C:\Users\Jedediah\.ssh\mcu-nex_key.pem" azureuser@YOUR.PUBLIC.IP
```

(The first line fixes the key file's permissions; Windows SSH refuses keys anyone else can read.
Type `yes` the first time it asks about the fingerprint.) You're in when the prompt says
`azureuser@mcu-nex:~$`.

## 5. Copy the setup script up

In a **second** PowerShell window on your PC:

```powershell
scp -i "C:\Users\Jedediah\.ssh\mcu-nex_key.pem" "C:\Users\Jedediah\Desktop\mcu-nex-main\deploy\server-setup.sh" azureuser@YOUR.PUBLIC.IP:~
```

## 6. Run the setup script (once)

On the server (the first window):

```bash
bash ~/server-setup.sh YOUR-ADDRESS
```

It installs Nginx, PHP 8.4, MariaDB and Certbot, adds swap memory (the server is small), creates
the database and its user (the password is saved in `~/.mcu-nex-db-password`), and sets up
`/var/www/mcu-nex`. Takes 5–10 minutes.

Then type `exit` and log in again (step 4's `ssh` line), so the new group setting applies.

## 7. Put the database in

Export a fresh copy of the database from phpMyAdmin on your PC (database `mcu_nex` → **Export**
→ Go), or use `mcu_nex.sql` from the project. Copy it up from your PC:

```powershell
scp -i "C:\Users\Jedediah\.ssh\mcu-nex_key.pem" "C:\Users\Jedediah\Desktop\mcu-nex-main\mcu_nex.sql" azureuser@YOUR.PUBLIC.IP:~
```

On the server:

```bash
mariadb -u mcu_nex -p"$(cat ~/.mcu-nex-db-password)" mcu_nex < ~/mcu_nex.sql
rm ~/mcu_nex.sql
```

(This only fills the server's own database. Your laptop's database isn't touched.)

## 8. Connect GitHub to the server

1. **A key just for GitHub** (on your PC, PowerShell):
   ```powershell
   ssh-keygen -t ed25519 -f "$HOME\.ssh\mcu-nex-deploy" -N '""' -C "github-deploy"
   type "$HOME\.ssh\mcu-nex-deploy.pub" | ssh -i "C:\Users\Jedediah\.ssh\mcu-nex_key.pem" azureuser@YOUR.PUBLIC.IP "cat >> ~/.ssh/authorized_keys"
   ```
2. On GitHub: the repo → **Settings → Secrets and variables → Actions**:
   - **Secrets** tab → *New repository secret*, three times:
     - `DEPLOY_HOST` = the server's public IP
     - `DEPLOY_USER` = `azureuser`
     - `DEPLOY_SSH_KEY` = the whole contents of `C:\Users\Jedediah\.ssh\mcu-nex-deploy` (the
       **private** file, the one *without* `.pub`, including the `-----BEGIN` and `END-----`
       lines). Open it with Notepad to copy it.
   - **Variables** tab → *New repository variable*: `DEPLOY_ENABLED` = `true`
3. Make sure the deploy files are on GitHub: push `main` (the workflow file has to be there).

## 9. The server's `.env`

On the server:

```bash
nano /var/www/mcu-nex/.env
```

Paste the contents of `deploy/env.production.example` (right-click pastes in PowerShell), then
change:
- `APP_URL=https://YOUR-ADDRESS`
- `DB_PASSWORD=` → the output of `cat ~/.mcu-nex-db-password`

Save: **Ctrl+O**, **Enter**, **Ctrl+X**.

The legacy API has its own database login file. On the server:

```bash
mkdir -p /var/www/mcu-nex/legacy-api && nano /var/www/mcu-nex/legacy-api/config.php
```

Paste `legacy-api/config.example.php`, then set `'user' => 'mcu_nex'` and `'pass' =>` the output
of `cat ~/.mcu-nex-db-password`. Save the same way.

## 10. First deploy

1. GitHub: repo → **Actions → Deploy to server → Run workflow**. Wait for the green check
   (3–5 minutes). If it's red, click it to see which step failed.
2. **Create the app key** (only once), on the server:
   ```bash
   cd /var/www/mcu-nex && php artisan key:generate --force && php artisan config:cache
   ```
3. Open **http://YOUR-ADDRESS** in your browser. You should see the login page.

## 11. HTTPS (free certificate)

On the server (use your real email, for expiry warnings):

```bash
sudo certbot --nginx -d YOUR-ADDRESS --redirect -m your@email.com --agree-tos -n
```

Now **https://YOUR-ADDRESS** works, and plain http goes there automatically. The certificate
renews itself. This is the link you give the panel.

---

## From now on

**Automatic:** push to `main` → GitHub builds the site and uploads it → it's live in 3–5 minutes
(repo → *Actions* shows each deploy). This also works when the backend dev pushes.

**Never touched by a deploy:** the server's `.env`, `storage/` (photos, sessions, logs) and the
database. Database changes (new tables or columns) you do by hand on the server, the same way you
do them in phpMyAdmin locally. See `deploy/rsync-exclude.txt`.

**When something breaks**, look at the logs on the server:
```bash
tail -n 100 /var/www/mcu-nex/storage/logs/laravel.log
sudo tail -n 50 /var/log/nginx/error.log
```

**Database backup** (do this before big changes):
```bash
mariadb-dump -u mcu_nex -p"$(cat ~/.mcu-nex-db-password)" mcu_nex > ~/backup-$(date +%F).sql
```

**Keep an eye on the credit:** portal → search **Subscriptions** → *Azure for Students* (or the
**Education** hub) shows how much of the $100 is left. Renew each year while you're a student.
Don't create a second server; that would use up the free hours twice as fast.

**Stopping it:** to take the site down for good, delete the `mcu-nex` resource group in the
portal (that removes everything at once).

## Before showing it to anyone (security)

- **Change every test or starting password**: the preview test password, the new Dean accounts'
  starting passwords, and any "12345"-style ones.
- Keep `APP_DEBUG=false` (error pages then don't show code).
- `mcu_nex.sql` on GitHub has real accounts and password hashes. Once the server has its
  database, remove it from GitHub, including its history. Keep the GitHub repo **private**.
