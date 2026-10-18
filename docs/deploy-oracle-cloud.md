# Putting MCU-NEX online (Oracle Cloud Always Free + GitHub Actions)

> **We're using Azure for Students instead** (Oracle's card check failed): see
> [deploy-azure.md](deploy-azure.md). This guide is kept in case Oracle works later; the
> scripts work on both.

When it's done, **every push to `main` on GitHub updates the live site by itself**.

You'll do the sign-ups and clicks yourself. This guide gives every step. The files it uses are
in `deploy/` and `.github/workflows/deploy.yml`.

Time: about 1–2 hours the first time. Cost: free. Oracle asks for a card to verify you; the
Always Free resources aren't charged. Free-tier rules change, so read Oracle's page when you
sign up.

---

## 1. Oracle Cloud account

1. Go to **https://www.oracle.com/cloud/free/** → **Start for free**.
2. Pick your **Home Region** carefully, because it can't be changed. For the Philippines,
   **Singapore** or **Japan (Tokyo/Osaka)** are close. If one is busy, the server step may say
   "out of capacity"; then you just try again later.
3. Finish the sign-up (email, card check). Wait for the "account is ready" email.

## 2. A free domain name (for HTTPS)

HTTPS needs a name, not just an IP address. A free one:

1. Go to **https://www.duckdns.org**, sign in (with Google or GitHub).
2. Add a subdomain, e.g. `mcu-nex` → you get **`mcu-nex.duckdns.org`**. Leave the IP for now;
   you'll fill it in at step 4.

(If you buy a real domain later, just point it at the same IP and rerun step 10.)

## 3. Create the server

In the Oracle Cloud console: **☰ → Compute → Instances → Create instance**.

| Setting | Choose |
|---|---|
| Name | `mcu-nex` |
| Image | **Canonical Ubuntu 24.04** (click *Change image*) |
| Shape | **Ampere → VM.Standard.A1.Flex**, **1 OCPU, 6 GB** memory (shows "Always Free eligible"). If it says *out of capacity*: try another *Availability domain*, try later, or use **VM.Standard.E2.1.Micro** (AMD, also free; smaller but works) |
| Networking | *Create new virtual cloud network* and *public subnet*; **Assign a public IPv4 address: Yes** |
| SSH keys | **Generate a key pair for me** → **Save private key** (and the public key). Keep the private key file safe; it's the only way in. |

Click **Create**. When it's *Running*, copy its **Public IP address**.

## 4. Open the web ports, and point the domain at the server

1. On the instance page: **Subnet** link → **Security Lists** → *Default Security List* →
   **Add Ingress Rules**, add two rules:
   - Source CIDR `0.0.0.0/0`, IP protocol TCP, destination port **80**
   - Source CIDR `0.0.0.0/0`, IP protocol TCP, destination port **443**
2. On **duckdns.org**: put the server's **Public IP** in your subdomain's *current ip* → **update ip**.

## 5. Log in to the server

On your PC (PowerShell), with the private key you saved:

```powershell
icacls "C:\path\to\ssh-key.key" /inheritance:r /grant:r "$($env:USERNAME):R"
ssh -i "C:\path\to\ssh-key.key" ubuntu@YOUR.PUBLIC.IP
```

(The first line fixes the key file's permissions; Windows SSH refuses keys anyone else can read.
Type `yes` the first time it asks about the fingerprint.)

## 6. Run the setup script (once)

In a **second** PowerShell window on your PC, copy the script up:

```powershell
scp -i "C:\path\to\ssh-key.key" "C:\Users\Jedediah\Desktop\mcu-nex-main\deploy\server-setup.sh" ubuntu@YOUR.PUBLIC.IP:~
```

Then, on the server:

```bash
bash ~/server-setup.sh mcu-nex.duckdns.org
```

It installs Nginx, PHP 8.4, MariaDB and Certbot, opens ports 80/443 on the server's own firewall,
creates the database and user (the password is saved in `~/.mcu-nex-db-password`), and sets up
`/var/www/mcu-nex`. Takes 5–10 minutes. Log out and back in afterwards (so the `www-data` group
applies).

## 7. Put the database in

From your PC, copy the database file up (or export a fresh one from phpMyAdmin first):

```powershell
scp -i "C:\path\to\ssh-key.key" "C:\Users\Jedediah\Desktop\mcu-nex-main\mcu_nex.sql" ubuntu@YOUR.PUBLIC.IP:~
```

On the server:

```bash
mariadb -u mcu_nex -p"$(cat ~/.mcu-nex-db-password)" mcu_nex < ~/mcu_nex.sql
rm ~/mcu_nex.sql
```

## 8. The server's `.env`

On the server:

```bash
nano /var/www/mcu-nex/.env
```

Paste the contents of `deploy/env.production.example`, then change:
- `APP_URL=https://mcu-nex.duckdns.org` (your address)
- `DB_PASSWORD=` → the output of `cat ~/.mcu-nex-db-password`

Save (Ctrl+O, Enter, Ctrl+X). The `APP_KEY` is filled in after the first deploy (step 9.5).

## 9. Connect GitHub to the server

1. **A key just for GitHub** (on your PC, PowerShell):
   ```powershell
   ssh-keygen -t ed25519 -f "$HOME\.ssh\mcu-nex-deploy" -N '""' -C "github-deploy"
   ```
   Copy the **public** key to the server:
   ```powershell
   type "$HOME\.ssh\mcu-nex-deploy.pub" | ssh -i "C:\path\to\ssh-key.key" ubuntu@YOUR.PUBLIC.IP "cat >> ~/.ssh/authorized_keys"
   ```
2. On GitHub: your repo → **Settings → Secrets and variables → Actions**:
   - **Secrets** tab → *New repository secret*, three times:
     - `DEPLOY_HOST` = the server's public IP
     - `DEPLOY_USER` = `ubuntu`
     - `DEPLOY_SSH_KEY` = the whole contents of `C:\Users\Jedediah\.ssh\mcu-nex-deploy` (the
       **private** file, without `.pub`, including the `-----BEGIN` / `END-----` lines)
   - **Variables** tab → *New repository variable*: `DEPLOY_ENABLED` = `true`
3. **First deploy:** repo → **Actions → Deploy to server → Run workflow** (or push to `main`).
   The first run fails at the very end with "No .env"? Then do step 8 and run it again.
4. Watch it finish (green check).
5. **Create the app key** (once), on the server:
   ```bash
   cd /var/www/mcu-nex && php artisan key:generate --force && php artisan config:cache
   ```
6. Open **http://mcu-nex.duckdns.org**. You should see the login page.

## 10. HTTPS (free certificate)

On the server:

```bash
sudo certbot --nginx -d mcu-nex.duckdns.org --redirect -m your@email.com --agree-tos -n
```

Now **https://mcu-nex.duckdns.org** works, and plain http redirects to it. The certificate
renews itself.

---

## From now on

- **Push to `main`** → GitHub builds and uploads it → the site updates in 2–4 minutes
  (repo → *Actions* shows each deploy).
- What the deploy **never touches** on the server: `.env`, `storage/` (uploads, sessions, logs),
  the database. See `deploy/rsync-exclude.txt`.
- **Logs** when something breaks: `tail -n 100 /var/www/mcu-nex/storage/logs/laravel.log`
  and `sudo tail -n 50 /var/log/nginx/error.log`.
- **Database backup** (do it before big changes):
  `mariadb-dump -u mcu_nex -p"$(cat ~/.mcu-nex-db-password)" mcu_nex > ~/backup-$(date +%F).sql`

## Before showing it to anyone (security)

- **Change every test or starting password**: the preview test password, the new Dean accounts'
  starting passwords, and any "12345"-style ones.
- Keep `APP_DEBUG=false` (error pages then don't show code).
- `mcu_nex.sql` on GitHub has real accounts and password hashes. Once the server has its
  database, remove it from GitHub (including its history). This is on the list for the
  security review.
- Oracle may reclaim Always Free servers that sit **idle** for a long time. A site people log
  into isn't idle, but check your Oracle email now and then.
