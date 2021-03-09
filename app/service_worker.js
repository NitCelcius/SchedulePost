var CACHE_NAME = "Schedulepost-cached-v4";
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
    caches.open(CACHE_NAME).then((CacheObj) => {
      return CacheObj.addAll(CacheURLs)
    })
  )
});

self.addEventListener("fetch", (Event) => {
    console.warn(Event.request);

  if (Event.request.method === "POST") {
    console.warn("POST!!!");
    Event.respondWith(
      fetch(Event.request.clone())
        .then(function (response) {
          console.warn(response);
          return response;
        })
    );
  } else {
    Event.respondWith(
      caches.match(Event.request).then((Resp) => {
        return Resp || fetch(Event.request)
      })
    )
  }
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