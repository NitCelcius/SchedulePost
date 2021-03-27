var CACHE_NAME = "Schedulepost-cached-v8";
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
            console.debug("response "+Event.request.url+" from cache");
            Event.waitUntil(fetch(Event.request).then((Resp) => {
              if (Resp.status >= 200 && Resp.status < 300) {
                console.debug("caching "+Event.request.url);
                CacheObj.put(Event.request, Resp);
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

    /*
    Event.respondWith(
      caches.match(Event.request).then((Resp) => {
        if (Resp) {
          // Exists
          //FetchAgain.headers.append("pragma", "no-cache");
          //FetchAgain.headers.append("cache-control", "no-cache");
          console.debug("trying:");
          console.debug(FetchAgain);
          fetch(new Request(Event.request.)).then((resp) => {
            const cc = resp.clone();
            if (cc.status >= 200 && cc.status < 300) {
              caches.open(CACHE_NAME).then(async (CacheObj) => {
                console.info("tried to cache " + Event.request.url);
//                CacheObj.put(Event.request, cc.clone());
              });
            }
          })
          //
          return Resp;
        } else {
          return fetch(Event.request).then((resp) => {
            const cc = resp.clone();
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
  */


function RespondFromCache(Request) {
  return caches.open(CACHE_NAME).then((CacheObj) => {
    return CacheObj.match(Request)
  })
}

function UpdateCache(Request) {
  return caches.open(CACHE_NAME).then((CacheObj) => {
    return fetch(Request.then((Resp) => {
      return CacheObj.put(Request, Resp.clone()).then(() => {
        return Resp;
      })
    }))
  })
}

self.addEventListener("activate", function (ev) {
  console.info("Activate!!!");
  ev.waitUntil(function () {
    console.warn(caches);
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