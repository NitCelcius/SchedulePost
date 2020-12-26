var Base_Header = document.getElementsByTagName("header")[0].childNodes;
var InterSectNodes = document.getElementsByClassName("InterSectTarget");
var CurrentInterSect = 0;
var InterSectIDs = new Array;
const InterSectObs = new IntersectionObserver(Scroll_Update, {
  root: null,
  rootMargin: "10px",
  threshold: [0.0, 1.0]
})

for (var i = 0; i < InterSectNodes.length; i++) {
  InterSectIDs.push(InterSectNodes[i].id);
  InterSectObs.observe(InterSectNodes[i]);
}

function Scroll_Update(Elements, Obj) {
  Elements.forEach(Node => {
    if (Node.isIntersecting === false) return;

    // console.info(Node.intersectionRect);

    var index = InterSectIDs.indexOf(Node.target.id);
    
    with (Node.intersectionRect) {

      console.info("index = "+index.toString()+" Ratio "+Node.intersectionRatio+" Pos "+top.toString() + "~" + bottom.toString());
      if ((Node.intersectionRatio < 0.1) && (top < 10)) {
        index = InterSectIDs.indexOf(Node.target.id) -1;
      }

      index = index >= 0 ? index : null;
    }
        /*
    if (Node.intersectionRatio < 0.1) {

      if (index >= 1) {
        Header_Update(Node.target.id);
      } else {
        Header_Update(null);
      }
    }


    if (Node.intersectionRatio > 0.9) {
      Header_Update(Node.target.id);
    }
*/
  });
}

function Header_Update(ElementID) {
  var Header = document.querySelector("header>p");
  switch (ElementID) {
    case "Table_Date":
      Header.innerHTML = "";
      Header.appendChild(document.createTextNode(document.getElementById("Table_Date").textContent));
      break;

    default:
      Header.innerHTML = "";
      Base_Header.forEach( Node => {
        Header.appendChild(Node);
      }) 
      break;
  }
}


/*
var Scroller_Timer;
Scroll_Update();
document.addEventListener("scroll", Scroller, {passive: true});
*/

/*
function Scroll_Update() {
  with (document.getElementById("Table_Date")) {
    this.Date_Top = offsetTop;
    this.Date_Bottom = offsetTop + offsetHeight;
  }
  
  this.rem = parseFloat(getComputedStyle(document.documentElement).fontSize);
}

function Scroller() {
  clearTimeout(Scroller_Timer);
  Scroller_Timer = setTimeout(function() {
    var ScreenY = window.pageYOffset + 3 * rem;
    var ScreenBottom = window.pageYOffset + window.innerHeight - 3 * rem;
    console.info(ScreenY);
    
    if (ScreenY > Date_Bottom) {
      ChangeHeader(document.createTextNode("hidden"));
      console.info("hidden");
    } else {
      ChangeHeader(null);
      console.info("shown");
    }
    
    
  }, 100);
}
*/

function Sidebar_Open() {
  document.getElementsByTagName("nav")[0].style.display = "block";
  document.getElementById("Nav_Overlay").style.display = "block";
}

function Sidebar_Close() {
  document.getElementsByTagName("nav")[0].style.display = "none";
  document.getElementById("Nav_Overlay").style.display = "none";
}

/*
var Potato = 0;

setInterval(function () {
  document.getElementsByTagName("body")[0].style.backgroundPositionX = Potato.toString() + "%";
  document.getElementsByTagName("body")[0].style.backgroundPositionY = Potato.toString() + "%";
  Potato = Potato + 0.1;
},30);
*/
