var CACHE_NAME = "Schedulepost-cached-v7";
var CacheURLs = [
  "/app/index.html",
  "/app/index.css",
  "/app/index.js",
  "/bin/web/lib.js",
  "/bin/web/commonloader.js",
  "/bin/web/navs.html",
  "/bin/web/theme.css",
  "/resources/images/clock.webp",
  "/resources/images/grid.webp",
  "/favicon.ico"
]

self.addEventListener("install", (Event) => {
  Event.waitUntil(
    caches.open(CACHE_NAME).then(async (CacheObj) => {
      skipWaiting();
      CacheObj.addAll(CacheURLs);
    })
  )
});

self.addEventListener("fetch", (Event) => {
  if (Event.request.method === "POST") {
    Event.respondWith(
      fetch(Event.request.clone())
      .then(function (response) {
        return response;
      })
    );
  } else {
    Event.respondWith(
      caches.match(Event.request).then((Resp) => {
        if (Resp) {
          return Resp;
        } else {
          return fetch(Event.request).then((resp) => {
            cc = resp.clone();
            if (cc.status >= 200 && cc.status < 300) {
              caches.open(CACHE_NAME).then(async (CacheObj) => {
                console.info("Cached " + Event.request.url);
                CacheObj.put(Event.request, cc.clone());
              });
            }
            return resp;
          })
        }
      })
    )
  }
});

self.addEventListener("activate", function (ev) {
  console.info("Activate!!!");
  ev.waitUntil(function () {
    console.warn(caches);
    caches.keys().then(function (Keys) {
      Keys.
      filter(() => {
          console.info(Keys);
          return Keys !== CACHE_NAME;
        })
        .map(function (Del) {
          return caches.delete(Del);
        })
    });
    navigator.serviceWorker.getRegistration().then((reg) => {
      reg.update();
    });
    clients.claim();
  });
});

/*
self.addEventListener("fetch", (Event) => {
  if (Event.request.method === "POST") {
    console.warn("POST!!!");
    Event.respondWith(
      fetch(Event.request.clone())
        .then(function (response) {
          console.warn(response);
          // Return the (fresh) response
          return response;
        }))
  } else {
    Event.respondWith(
      caches.match(Event.request).then((Resp) => {
        return Resp || fetch(Event.request)
      })
    )
  }
});
*/