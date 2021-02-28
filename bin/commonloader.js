async function LoadCommonNodes() {
  // Load until successful attempt - is this correct, or no?
  var CommonRaw;
  while (true) {
    try {
      CommonRaw = await AwaitLoady("/Resources/Common/Navs.html");


      if (CommonRaw["Content"] != null) {
        break;
      }
    } catch (e) {
      setTimeout(2000);
      // Do nothing, retry!
    }
  }

  var Target = document.getElementsByTagName("body")[0];

  /*
  Field = document.createElement("div");
  Field.innerHTML = CommonRaw["Content"];
  console.info(Field.childNodes);

  for (var i = 0; i < Field.children.length; i++) {
    console.info(Field.children[i]);
    Target.appendChild(Field.children[i]);
  }
  */
  
  Field = document.createElement("div");
  Field.innerHTML = CommonRaw["Content"];

  for (var i = 0; i < Field.childNodes.length; i++) {
    Target.appendChild(Field.childNodes[i]);
  }

}

LoadCommonNodes();