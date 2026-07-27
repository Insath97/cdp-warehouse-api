<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="{{ $app_name }} — Warehouse Management REST API. Version {{ $app_version }}." />
    <title>{{ $app_name }} API</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --yellow: #FFF345;
            --cream:  #EAE6DA;
            --dark:   #111110;
            --muted:  #6B6B65;
            --card-bg: rgba(255,255,255,0.6);
            --border:  rgba(0,0,0,0.08);
        }

        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--cream);
            color: var(--dark);
        }

        /* ─── BACKGROUND GLOW DRIFT ─── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 20% 20%, rgba(255,243,69,0.3) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 80%, rgba(255,243,69,0.2) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
            animation: bgDrift 20s ease-in-out infinite alternate;
        }

        @keyframes bgDrift {
            0%   { transform: translate(0, 0) scale(1); }
            50%  { transform: translate(-25px, 20px) scale(1.05); }
            100% { transform: translate(20px, -15px) scale(1); }
        }

        /* ─── NOISE OVERLAY ─── */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.4;
        }

        /* ─── CANVAS PARTICLE CONSTELLATION ─── */
        #particle-canvas {
            position: absolute;
            inset: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            display: block;
        }

        /* ─── TOP RIGHT VERSION BADGE (RS-EXPRESS PATTERN) ─── */
        .version-badge {
            position: absolute;
            top: 24px;
            right: 28px;
            z-index: 10;
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.05);
            animation: fadeIn 0.8s ease forwards;
        }

        /* ─── CENTER HERO CONTAINER (RS-EXPRESS PATTERN) ─── */
        .hero-container {
            position: relative;
            z-index: 10;
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 0 24px;
        }

        .hero-sublabel {
            font-size: 16px;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.02em;
            margin-bottom: 12px;
            opacity: 0;
            animation: fadeUp 0.8s 0.2s ease forwards;
        }

        h1.hero-title {
            font-size: clamp(42px, 6.5vw, 76px);
            font-weight: 900;
            letter-spacing: -2px;
            line-height: 1.1;
            color: var(--dark);
            margin-bottom: 20px;
            opacity: 0;
            animation: fadeUp 0.8s 0.4s ease forwards;
        }

        h1.hero-title em {
            font-style: normal;
            background: linear-gradient(135deg, #a89a00 0%, #FFF345 50%, #d4c800 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
    </style>
</head>
<body>

<!-- TOP RIGHT VERSION BADGE (RS-EXPRESS PATTERN) -->
<div class="version-badge">v{{ $app_version }}</div>

<!-- FULLSCREEN PARTICLE CANVAS -->
<canvas id="particle-canvas"></canvas>

<!-- CENTERED HERO (RS-EXPRESS PATTERN WITH CENTRIX CONTENT & COLORS) -->
<main class="hero-container">
    <div class="hero-sublabel">Backend Deployment Status</div>
    <h1 class="hero-title">{{ $app_name }}</h1>
</main>

<script>
(function () {
    // ─── FULLSCREEN PARTICLE CONSTELLATION NETWORK ───
    var canvas = document.getElementById('particle-canvas');
    var ctx = canvas.getContext('2d');
    var particles = [];
    var mouse = { x: null, y: null };
    var PARTICLE_COUNT = 95;
    var CONNECT_DIST = 170;
    var MOUSE_RADIUS = 200;

    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    window.addEventListener('mousemove', function (e) {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
    });

    window.addEventListener('mouseleave', function () {
        mouse.x = null;
        mouse.y = null;
    });

    function Particle() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.vx = (Math.random() - 0.5) * 0.9;
        this.vy = (Math.random() - 0.5) * 0.9;
        this.radius = Math.random() * 2 + 1;
    }

    Particle.prototype.update = function () {
        this.x += this.vx;
        this.y += this.vy;

        if (this.x < 0 || this.x > canvas.width) this.vx *= -1;
        if (this.y < 0 || this.y > canvas.height) this.vy *= -1;

        if (mouse.x !== null) {
            var dx = mouse.x - this.x;
            var dy = mouse.y - this.y;
            var dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < MOUSE_RADIUS) {
                var force = (MOUSE_RADIUS - dist) / MOUSE_RADIUS * 0.025;
                this.vx += dx * force;
                this.vy += dy * force;
            }
        }

        var speed = Math.sqrt(this.vx * this.vx + this.vy * this.vy);
        if (speed > 1.6) {
            this.vx = (this.vx / speed) * 1.6;
            this.vy = (this.vy / speed) * 1.6;
        }
    };

    Particle.prototype.draw = function () {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(107, 107, 101, 0.45)';
        ctx.fill();
    };

    for (var i = 0; i < PARTICLE_COUNT; i++) {
        particles.push(new Particle());
    }

    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        for (var i = 0; i < particles.length; i++) {
            particles[i].update();
            particles[i].draw();

            for (var j = i + 1; j < particles.length; j++) {
                var dx = particles[i].x - particles[j].x;
                var dy = particles[i].y - particles[j].y;
                var dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < CONNECT_DIST) {
                    var opacity = (1 - dist / CONNECT_DIST) * 0.28;
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.strokeStyle = 'rgba(107, 107, 101, ' + opacity + ')';
                    ctx.lineWidth = 0.8;
                    ctx.stroke();
                }
            }
        }
        requestAnimationFrame(animate);
    }
    animate();
})();
</script>
</body>
</html>
