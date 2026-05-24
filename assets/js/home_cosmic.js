(() => {
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const hero = document.querySelector('[data-home-cosmos]');
  const canvas = document.querySelector('[data-home-cosmic-canvas]');

  const revealItems = document.querySelectorAll('[data-home-reveal]');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.18 });
    revealItems.forEach((item) => observer.observe(item));
  } else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  }

  if (!hero || reduceMotion) return;

  const orbitStage = hero.querySelector('.home-orbit-stage');
  const relic = hero.querySelector('.home-cosmic-relic');
  hero.addEventListener('pointermove', (event) => {
    const rect = hero.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
    const y = ((event.clientY - rect.top) / rect.height - 0.5) * 2;
    if (orbitStage) orbitStage.style.transform = `rotateY(${x * 5}deg) rotateX(${-y * 4}deg)`;
    if (relic) relic.style.setProperty('--relic-glow-x', `${50 + x * 12}%`);
  }, { passive: true });

  hero.addEventListener('pointerleave', () => {
    if (orbitStage) orbitStage.style.transform = '';
  });

  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  let width = 0;
  let height = 0;
  let dpr = 1;
  let stars = [];
  let meteors = [];

  const makeStar = () => ({
    x: Math.random() * width,
    y: Math.random() * height,
    r: Math.random() * 1.8 + 0.25,
    vx: (Math.random() - 0.5) * 0.12,
    vy: Math.random() * 0.08 + 0.015,
    a: Math.random() * 0.7 + 0.25,
    tw: Math.random() * Math.PI * 2
  });

  const resize = () => {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = Math.max(1, hero.offsetWidth);
    height = Math.max(1, hero.offsetHeight);
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const count = Math.min(180, Math.max(80, Math.floor(width * height / 6500)));
    stars = Array.from({ length: count }, makeStar);
  };

  const makeMeteor = () => ({
    x: Math.random() * width * 0.75 + width * 0.25,
    y: Math.random() * height * 0.35,
    len: Math.random() * 90 + 60,
    speed: Math.random() * 5 + 5,
    life: 0,
    maxLife: Math.random() * 36 + 26,
  });

  const draw = () => {
    ctx.clearRect(0, 0, width, height);
    const gradient = ctx.createRadialGradient(width * 0.64, height * 0.42, 0, width * 0.64, height * 0.42, Math.max(width, height) * 0.62);
    gradient.addColorStop(0, 'rgba(119,231,255,0.11)');
    gradient.addColorStop(0.42, 'rgba(168,117,255,0.04)');
    gradient.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, width, height);

    stars.forEach((star) => {
      star.x += star.vx;
      star.y += star.vy;
      star.tw += 0.018;
      if (star.y > height + 8) star.y = -8;
      if (star.x < -8) star.x = width + 8;
      if (star.x > width + 8) star.x = -8;
      const alpha = star.a * (0.65 + Math.sin(star.tw) * 0.35);
      ctx.beginPath();
      ctx.fillStyle = `rgba(235,248,255,${Math.max(0.08, alpha)})`;
      ctx.arc(star.x, star.y, star.r, 0, Math.PI * 2);
      ctx.fill();
    });

    if (Math.random() < 0.012 && meteors.length < 3) meteors.push(makeMeteor());
    meteors = meteors.filter((meteor) => meteor.life < meteor.maxLife);
    meteors.forEach((meteor) => {
      meteor.life += 1;
      meteor.x -= meteor.speed;
      meteor.y += meteor.speed * 0.38;
      const alpha = 1 - meteor.life / meteor.maxLife;
      const grd = ctx.createLinearGradient(meteor.x, meteor.y, meteor.x + meteor.len, meteor.y - meteor.len * 0.38);
      grd.addColorStop(0, `rgba(119,231,255,${0.75 * alpha})`);
      grd.addColorStop(1, 'rgba(119,231,255,0)');
      ctx.strokeStyle = grd;
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.moveTo(meteor.x, meteor.y);
      ctx.lineTo(meteor.x + meteor.len, meteor.y - meteor.len * 0.38);
      ctx.stroke();
    });

    requestAnimationFrame(draw);
  };

  resize();
  window.addEventListener('resize', resize, { passive: true });
  draw();
})();
