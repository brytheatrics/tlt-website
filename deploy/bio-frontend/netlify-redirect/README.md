# Netlify → tacomalittletheatre.com/bio/ redirect

Drop the `_redirects` file next to `index.html` in the Netlify project source
(`G:\My Drive\WebApp Production\tlt-bio-submission\`) and redeploy the site
from Netlify. That's it — every request to `tlt-bio.netlify.app/*` will 301 to
`tacomalittletheatre.com/bio/*` with the query string preserved.

## Test cases

- `https://tlt-bio.netlify.app/?token=X` → `https://tacomalittletheatre.com/bio/?token=X`
- `https://tlt-bio.netlify.app/emergency-info?token=X` → `https://tacomalittletheatre.com/bio/emergency-info?token=X`
  (Cloudways will 301 the no-slash path to `/emergency-info/` — two hops total, but works.)

## Notes

- The `!` on `301!` forces the redirect even if `index.html` still exists in
  the Netlify build. That's why we don't need to strip the old files —
  Netlify will just never serve them.
- If you want to save space, you can delete `index.html`, `emergency-info.html`,
  and `logo.png` from the Netlify project after confirming redirects work.
- Keep `_redirects` (no extension) at the root of the deploy — Netlify picks
  it up automatically.
