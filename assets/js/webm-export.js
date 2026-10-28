(function () {
  const button = document.getElementById('export-webm');
  const video = document.querySelector('.videobox video');
  const canvas = document.getElementById('webm-canvas');
  const status = document.getElementById('webm-status');
  const progress = document.getElementById('webm-progress');
  const data = document.getElementById('webm-export-data');
  if (!button || !video || !canvas || !status || !progress || !data || button.disabled) return;

  let cues;
  try {
    cues = JSON.parse(data.dataset.cues || '[]');
  } catch (_) {
    status.textContent = 'اطلاعات زیرنویس قابل خواندن نیست.';
    button.disabled = true;
    return;
  }
  const projectId = data.dataset.projectId || 'project';
  const say = (message) => { status.textContent = message; };

  function drawCaption(ctx, text, width, height) {
    const size = Math.max(26, Math.min(62, width * 0.043));
    const lineHeight = size * 1.32;
    ctx.font = '700 ' + size + 'px Vazirmatn, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    if ('direction' in ctx) ctx.direction = 'rtl';
    const maxWidth = width * 0.86;
    const words = text.split(/\s+/);
    const lines = [];
    let line = '';
    words.forEach((word) => {
      const test = line ? line + ' ' + word : word;
      if (line && ctx.measureText(test).width > maxWidth) {
        lines.push(line);
        line = word;
      } else line = test;
    });
    if (line) lines.push(line);
    const visible = lines.slice(-3);
    const boxWidth = Math.min(maxWidth + size, Math.max(...visible.map(lineText => ctx.measureText(lineText).width)) + size * 1.1);
    const boxHeight = visible.length * lineHeight + size * 0.48;
    const x = (width - boxWidth) / 2;
    const y = height - height * 0.075 - boxHeight;
    ctx.fillStyle = 'rgba(0,0,0,.68)';
    ctx.fillRect(x, y, boxWidth, boxHeight);
    ctx.fillStyle = '#fff';
    visible.forEach((lineText, i) => ctx.fillText(lineText, width / 2, y + size * 0.24 + lineHeight * (i + 0.5)));
  }

  function renderFrame(ctx) {
    if (video.videoWidth && video.videoHeight) {
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const now = video.currentTime;
      const cue = cues.find(item => now >= Number(item.start) && now < Number(item.end));
      if (cue) drawCaption(ctx, String(cue.text || ''), canvas.width, canvas.height);
    }
    if (!video.paused && !video.ended) {
      progress.value = video.duration ? Math.min(99, Math.round(video.currentTime / video.duration * 100)) : 0;
      requestAnimationFrame(() => renderFrame(ctx));
    }
  }

  button.addEventListener('click', async function () {
    if (!window.MediaRecorder || !canvas.captureStream || !(video.captureStream || video.mozCaptureStream)) {
      say('این مرورگر ضبط WebM را پشتیبانی نمی‌کند. از نسخه جدید Chrome یا Firefox استفاده کنید.');
      return;
    }
    button.disabled = true;
    progress.value = 0;
    let recorder;
    let stream;
    let audioStream;
    try {
      say('در حال آماده‌سازی ویدئو و زیرنویس‌ها…');
      if (document.fonts && document.fonts.ready) await document.fonts.ready;
      if (video.readyState < 2) await new Promise((resolve, reject) => {
        video.addEventListener('loadeddata', resolve, {once:true});
        video.addEventListener('error', reject, {once:true});
      });
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      if (!canvas.width || !canvas.height) throw new Error('ابعاد ویدئو قابل خواندن نیست.');
      video.pause();
      if (video.currentTime !== 0) await new Promise(resolve => {
        video.addEventListener('seeked', resolve, {once:true});
        video.currentTime = 0;
      });
      const ctx = canvas.getContext('2d', {alpha:false});
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      stream = canvas.captureStream(30);
      audioStream = (video.captureStream || video.mozCaptureStream).call(video);
      const audioTracks = audioStream.getAudioTracks();
      audioTracks.forEach(track => stream.addTrack(track));
      const types = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm'];
      const mimeType = types.find(type => MediaRecorder.isTypeSupported(type)) || '';
      const chunks = [];
      recorder = new MediaRecorder(stream, mimeType ? {mimeType} : undefined);
      recorder.ondataavailable = event => { if (event.data && event.data.size) chunks.push(event.data); };
      recorder.onerror = () => say('هنگام ساخت فایل WebM خطایی رخ داد. فضای آزاد و پشتیبانی مرورگر را بررسی کنید.');
      recorder.onstop = () => {
        if (chunks.length) {
          const blob = new Blob(chunks, {type: recorder.mimeType || 'video/webm'});
          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = 'opencaption-' + projectId + '.webm';
          document.body.appendChild(link);
          link.click();
          link.remove();
          setTimeout(() => URL.revokeObjectURL(url), 60000);
          progress.value = 100;
          say('فایل WebM ساخته شد.' + (audioTracks.length ? ' صدا نیز ضبط شده است.' : ' هشدار: مرورگر ترک صدای قابل ضبط ارائه نکرد؛ فایل ممکن است بی‌صدا باشد.'));
        } else say('فایل خروجی ساخته نشد. دوباره تلاش کنید.');
        if (stream) stream.getTracks().forEach(track => track.stop());
        if (audioStream) audioStream.getTracks().forEach(track => track.stop());
        button.disabled = false;
      };
      recorder.start(1000);
      say('در حال رندر در مرورگر… ویدئو را تا پایان پخش نگه دارید.');
      video.addEventListener('ended', () => { if (recorder && recorder.state !== 'inactive') recorder.stop(); }, {once:true});
      await video.play();
      requestAnimationFrame(() => renderFrame(ctx));
    } catch (error) {
      if (recorder && recorder.state !== 'inactive') recorder.stop();
      else {
        if (stream) stream.getTracks().forEach(track => track.stop());
        if (audioStream) audioStream.getTracks().forEach(track => track.stop());
        button.disabled = false;
      }
      say('ساخت WebM ناموفق بود: ' + (error && error.message ? error.message : 'خطای مرورگر'));
    }
  });
})();
