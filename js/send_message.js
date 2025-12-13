document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("messageForm");
  const textarea = document.getElementById("messageContent");
  const responseBox = document.getElementById("response");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const content = textarea.value.trim();

    if (!content) {
      alert("請輸入留言內容！");
      return;
    }

    try {
      const res = await fetch("post_message.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({ content })
      });

      const text = await res.text();
      responseBox.textContent = text;
      textarea.value = "";
    } catch (err) {
      responseBox.textContent = "留言失敗，請稍後再試。";
    }
  });
});
