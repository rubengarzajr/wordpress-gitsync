gitsync.popup = function(){
  var parentDiv = document.body;
  var bkDiv = document.createElement("div");
  bkDiv.id = "GS-popup-bk";

  const popup  = document.createElement("div");
  popup.id = "GS-popup";

  const title = document.createElement("div");
  title.className = "GS-popup-title"
  const titleContent = document.createTextNode(gitsync.title);
  title.appendChild(titleContent);

  const titleX = document.createElement("div");
  titleX.className = "GS-close"
  title.appendChild(titleX);
  titleX.addEventListener("click", function(){gitsync.close();});

  const message = document.createElement("div");
  message.className = "GS-popup-message"
  const messageContent = document.createTextNode(gitsync.message);
  message.appendChild(messageContent);

  popup.appendChild(title);
  popup.appendChild(message);

  const currentDiv = document.getElementById("div1");
  parentDiv.prepend(popup);
  parentDiv.prepend(bkDiv);
}

gitsync.close = function(){
  document.getElementById('GS-popup').style.display = 'none';
  document.getElementById('GS-popup-bk').style.display = 'none';
}


if (gitsync.title !== ''){
  gitsync.popup();
}
