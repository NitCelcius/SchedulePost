var CACHE_NAME = "Schedulepost-cached-v8-2";
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
  "/favicon.ico",
  "/resources/favicon/favicon-192x192.png",
  "/app/manifest.json"
]

self.addEventListener("install", (Event) => {
  Event.waitUntil(
    caches.open(CACHE_NAME).then(async (CacheObj) => {
      CacheObj.addAll(CacheURLs);
      skipWaiting();
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
            cr = Event.request.clone();
            Event.waitUntil(fetch(cr).then((Cresp) => {
              if (Cresp.status >= 200 && Cresp.status < 300) {
                console.debug("caching " + Event.request.url);
                //CacheObj.put(Event.request, Cresp);
              } else {
                console.error("!?");
                console.error(Event.request);
              }
            }));
            return Resp;
          } else {
            console.debug("Fetching " + Event.request.url);
            cr = Event.request.clone();
            //console.warn(cr);
            if (cr.url === "http://localhost:84/app/index.html") {
              console.error(cr);
              //throw Error("!!!!!!!!!!");
            }

            return fetch(cr).then((Cresp) => {
              CacheReq = Cresp.clone();
              if (CacheReq.status >= 200 && CacheReq.status < 300) {
                console.debug("caching " + Event.request.url);
                //CacheObj.put(Event.request, CacheReq);
              }
              return Cresp;
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
      clients.claim();
    });
  });
});