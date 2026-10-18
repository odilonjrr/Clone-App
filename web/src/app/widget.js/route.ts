// Script de instalação do widget (F03). Escrito à mão, sem dependências:
// cria um iframe para o chat, isolando o CSS do site do cliente.
const script = String.raw`(function () {
  var s = document.currentScript;
  if (!s || window.__atendenteIA) return;
  window.__atendenteIA = true;
  var key = s.getAttribute("data-key");
  var mode = s.getAttribute("data-mode") === "inline" ? "inline" : "bubble";
  var origin = new URL(s.src).origin;
  var src = origin + "/w/" + encodeURIComponent(key) + "?mode=" + mode + "&host=" + encodeURIComponent(location.hostname);
  var frame = document.createElement("iframe");
  frame.src = src;
  frame.title = "Chat";
  frame.setAttribute("allow", "clipboard-write");
  frame.style.cssText = "border:0;background:transparent;color-scheme:normal;";
  if (mode === "inline") {
    var target = document.getElementById(s.getAttribute("data-target") || "atendente-ia");
    if (!target) return console.warn("[widget] elemento de destino não encontrado");
    frame.style.width = "100%";
    frame.style.height = "100%";
    target.appendChild(frame);
    return;
  }
  var box = document.createElement("div");
  box.style.cssText = "position:fixed;bottom:16px;right:16px;width:88px;height:88px;z-index:2147483000;";
  box.appendChild(frame);
  frame.style.width = "100%";
  frame.style.height = "100%";
  document.body.appendChild(box);
  window.addEventListener("message", function (e) {
    if (e.origin !== origin || !e.data || e.data.type !== "atendente:layout") return;
    var d = e.data;
    var small = window.innerWidth < 480;
    box.style.left = d.position === "bottom-left" ? "16px" : "auto";
    box.style.right = d.position === "bottom-left" ? "auto" : "16px";
    if (d.open && small) {
      box.style.cssText += ";inset:0;width:100%;height:100%;bottom:0;right:0;left:0;";
    } else {
      box.style.top = "auto";
      box.style.bottom = "16px";
      box.style.width = d.open ? "400px" : "88px";
      box.style.height = d.open ? "min(640px, calc(100vh - 32px))" : "88px";
    }
  });
})();`;

export function GET() {
  return new Response(script, {
    headers: {
      "content-type": "text/javascript; charset=utf-8",
      "cache-control": "public, max-age=300",
      "access-control-allow-origin": "*",
    },
  });
}
