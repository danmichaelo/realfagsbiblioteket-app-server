
Lightweight server for the
[Realfagsbiblioteket app](https://github.com/scriptotek/realfagsbiblioteket-app/),
built on Lumen.

- `/status` allows us to present a status message in the app if something is
  wrong. Returns 'ok' if everything's fine.

- `/search` proxies the search to the desired search backend, currently
  [LSM](https://github.com/scriptotek/lsm/).
