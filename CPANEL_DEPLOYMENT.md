# Judylink cPanel deployment

The Git repository and the public website should use different directories.

- Git repository: choose a private directory under `/home/zanexcel`, for example
  `/home/zanexcel/repositories/Judylink`.
- Website document root: `/home/zanexcel/judylink.zanexcellent.com`.

The tracked `.cpanel.yml` installs the locked Node dependencies, builds the Vite
frontend and admin stylesheet, creates the production package, and copies that
package into the website document root.

## One-time backend configuration

`backend/config.php` contains database credentials and is deliberately excluded
from Git and from the generated deployment package. Before the first deployment:

1. Create `/home/zanexcel/judylink.zanexcellent.com/backend/config.php`.
2. Start with `backend/config.cpanel.example.php` from the repository.
3. Enter the cPanel database name, database user, database password, admin
   credentials, and the final application URL.
4. Set `APP_URL` to `https://judylink.zanexcellent.com`.
5. Keep the file permissions restricted to the cPanel account.

The deployment checks that this file exists before publishing. Deployments copy
new application files over the existing document root, so `backend/config.php`
and existing files under `backend/uploads/` are preserved.

## cPanel setup

1. In **Git Version Control**, clone the repository outside the document root.
2. Ensure a supported Node.js release and `npm` are available to deployment
   tasks.
3. Enable automatic deployment for the repository's production branch.
4. Deploy the current commit.
5. Verify:
   - `https://judylink.zanexcellent.com/`
   - `https://judylink.zanexcellent.com/backend/api/health.php`
   - `https://judylink.zanexcellent.com/backend/secure-operation-4f2a/`

If cPanel reports that `npm` is unavailable, configure the repository's deploy
task to use the absolute `npm` path provided by the cPanel Node.js application.

