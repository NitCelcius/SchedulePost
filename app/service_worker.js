var CACHE_NAME = "Schedulepost-cached-v8-1";
var CacheURLs = [
  "/app/", // aka
  "/app/index.html",
  "/app/index.css",
  "/app/index.js",
  "/bin/web/lib.js",
  "/bin/web/commonloader.js",
  "/bin/web/navs.html",
  "/bin/web/theme.css",
  "/resources/images/clock.webp",
  "/resources/images/grid.webp",
  "/resources/images/Updates.webp",
  "/resources/images/clock.png",
  "/resources/images/grid.png",
  "/resources/images/Updates.png",
  "/favicon.ico"
]

self.addEventListener("install", (Event) => {
  Event.waitUntil(
    caches.open(CACHE_NAME).then(async (CacheObj) => {
      skipWaiting();
      CacheObj.addAll(CacheURLs);
    })
  );
});

self.addEventListener("fetch", (Event) => {
  if (Event.request.method === "POST") {
    Event.respondWith(
      fetch(Event.request.clone())
        .then(function (response) {
          return response;
        })
    );
  } else if (Event.request.method === "GET") {
    Event.respondWith(
      caches.open(CACHE_NAME).then((CacheObj) => {
        return CacheObj.match(Event.request).then((Resp) => {
          if (Resp) {
            console.debug("response " + Event.request.url + " from cache");
            console.debug(Event.request);
            Event.waitUntil(fetch(Event.request).then((Resp) => {
              if (Resp.status >= 200 && Resp.status < 300) {
                console.debug("caching " + Event.request.url);
                CacheObj.put(Event.request, Resp);
              } else {
                console.error("!?");
                console.error(Event.request);
              }
            }));
            return Resp;
          } else {
            console.debug("Fetching " + Event.request.url);
            return fetch(Event.request).then((Resp) => {
              if (Resp.status >= 200 && Resp.status < 300) {
                console.debug("caching " + Event.request.url);
                CacheObj.put(Event.request, Resp);
              }
              return Resp;
            })
          }
        })
      })
    )
  }
});

self.addEventListener("activate", function (ev) {
  console.info("Activate!!!");
  ev.waitUntil(function () {
    caches.keys().then(function (Keys) {
      Keys.filter(() => {
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