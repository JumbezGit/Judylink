import { cp, mkdir, readdir, readFile, rm, writeFile } from 'node:fs/promises'
import path from 'node:path'

const root = process.cwd()
const packageRoot = path.join(root, 'deploy', 'cpanel-package')
const publicHtml = path.join(packageRoot, 'public_html')
const backendSource = path.join(root, 'backend')
const backendTarget = path.join(publicHtml, 'backend')

await rm(packageRoot, { recursive: true, force: true })
await mkdir(publicHtml, { recursive: true })
await cp(path.join(root, 'dist'), publicHtml, { recursive: true })

const excludedBackendEntries = new Set([
  'config.php',
  'config.cpanel.example.php',
  'config.example.php',
  'database',
  'php.ini',
  'README.md',
  'tmp',
])

await mkdir(backendTarget, { recursive: true })
for (const entry of await readdir(backendSource, { withFileTypes: true })) {
  if (excludedBackendEntries.has(entry.name)) continue
  await cp(path.join(backendSource, entry.name), path.join(backendTarget, entry.name), { recursive: true })
}

await cp(
  path.join(backendSource, 'config.cpanel.example.php'),
  path.join(backendTarget, 'config.php.example'),
)
await mkdir(path.join(backendTarget, 'uploads'), { recursive: true })
await writeFile(
  path.join(backendTarget, 'uploads', '.htaccess'),
  `Options -Indexes\n\n<FilesMatch "\\.(?:php[0-9]?|phtml|phar)$">\n  Require all denied\n</FilesMatch>\n`,
)

await mkdir(path.join(packageRoot, 'database'), { recursive: true })
await cp(path.join(backendSource, 'database', 'schema.sql'), path.join(packageRoot, 'database', 'schema.sql'))
await cp(path.join(root, 'CPANEL_DEPLOYMENT.md'), path.join(packageRoot, 'DEPLOYMENT.md'))

const index = await readFile(path.join(publicHtml, 'index.html'), 'utf8')
if (!index.includes('/assets/')) {
  throw new Error('Frontend build verification failed: index.html has no production asset references.')
}

console.log(`cPanel package created at ${packageRoot}`)
