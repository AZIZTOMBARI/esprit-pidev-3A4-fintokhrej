<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\KernelInterface;

class SortieRecapGenerator
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $mediaRows
     *
     * @return array{path:string, mode:string}
     */
    public function generate(array $sortie, array $mediaRows, int $version): array
    {
        $publicDir = $this->kernel->getProjectDir().DIRECTORY_SEPARATOR.'public';
        $recapDirectory = $publicDir.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'experiences'.DIRECTORY_SEPARATOR.'recaps';

        if (!is_dir($recapDirectory)) {
            mkdir($recapDirectory, 0777, true);
        }

        $relativePath = sprintf('/uploads/experiences/recaps/sortie-%d-v%d.html', (int) $sortie['id'], $version);
        $absolutePath = $publicDir.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        file_put_contents($absolutePath, $this->buildImmersiveHtml($sortie, $mediaRows));

        return [
            'path' => $relativePath,
            'mode' => $this->hasFfmpeg() ? 'hybrid' : 'immersive_html',
        ];
    }

    public function hasFfmpeg(): bool
    {
        $finder = new ExecutableFinder();

        return $finder->find('ffmpeg') !== null;
    }

    public function validateVideoDuration(UploadedFile $file, int $maxSeconds = 60): bool
    {
        $finder = new ExecutableFinder();
        $ffprobe = $finder->find('ffprobe');

        if ($ffprobe === null) {
            return true;
        }

        $process = new Process([
            $ffprobe,
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'default=noprint_wrappers=1:nokey=1',
            $file->getPathname(),
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            return true;
        }

        $duration = (float) trim($process->getOutput());

        return $duration <= $maxSeconds;
    }

    /**
     * @param array<int, array<string, mixed>> $mediaRows
     */
    private function buildImmersiveHtml(array $sortie, array $mediaRows): string
    {
        $slides = [];

        foreach ($mediaRows as $mediaRow) {
            $slides[] = [
                'type' => (string) ($mediaRow['media_type'] ?? 'image'),
                'src' => (string) ($mediaRow['file_path'] ?? ''),
                'author' => trim((string) (($mediaRow['prenom'] ?? '').' '.($mediaRow['nom'] ?? ''))),
                'uploadedAt' => (string) ($mediaRow['uploaded_at'] ?? ''),
            ];
        }

        $payload = json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $title = htmlspecialchars((string) ($sortie['titre'] ?? 'Souvenirs de sortie'), ENT_QUOTES);
        $place = htmlspecialchars((string) ($sortie['ville'] ?? ''), ENT_QUOTES);
        $date = !empty($sortie['date_sortie'])
            ? (new \DateTimeImmutable((string) $sortie['date_sortie']))->format('d/m/Y H:i')
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        :root {
            color-scheme: dark;
            --bg-1: #081120;
            --bg-2: #133155;
            --glass: rgba(255,255,255,0.12);
            --line: rgba(255,255,255,0.18);
            --text: #f8fbff;
            --muted: rgba(240,247,255,0.78);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            overflow: hidden;
            font-family: "Segoe UI", "SF Pro Display", system-ui, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top, rgba(94, 196, 255, 0.38), transparent 32%),
                radial-gradient(circle at bottom left, rgba(255, 180, 120, 0.28), transparent 28%),
                linear-gradient(135deg, var(--bg-1), var(--bg-2) 48%, #0b1627);
        }
        .ambient {
            position: fixed;
            inset: -20%;
            background:
                radial-gradient(circle, rgba(135, 207, 255, 0.18), transparent 42%),
                radial-gradient(circle at 70% 30%, rgba(255, 170, 120, 0.18), transparent 36%);
            filter: blur(42px);
            animation: drift 16s linear infinite alternate;
            pointer-events: none;
        }
        .frame {
            position: relative;
            min-height: 100vh;
            padding: 24px;
            display: grid;
            place-items: center;
        }
        .story {
            position: relative;
            width: min(100%, 1120px);
            aspect-ratio: 16 / 9;
            border-radius: 34px;
            overflow: hidden;
            border: 1px solid var(--line);
            background: rgba(5, 10, 18, 0.55);
            box-shadow: 0 36px 100px rgba(0, 0, 0, 0.45);
        }
        .slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 1.2s ease, transform 7s ease;
            transform: scale(1.08);
        }
        .slide.active {
            opacity: 1;
            transform: scale(1);
        }
        .slide img,
        .slide video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            filter: saturate(1.08) contrast(1.02);
        }
        .overlay {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(5,10,18,0.15), rgba(5,10,18,0.2) 30%, rgba(5,10,18,0.55)),
                linear-gradient(0deg, rgba(5,10,18,0.18), transparent 32%);
        }
        .hero {
            position: absolute;
            inset: 0;
            padding: clamp(22px, 4vw, 46px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
            padding: 10px 16px;
            border-radius: 999px;
            backdrop-filter: blur(22px);
            background: var(--glass);
            border: 1px solid var(--line);
            color: var(--muted);
            font-size: 0.92rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .title-wrap h1 {
            margin: 0;
            max-width: 12ch;
            font-size: clamp(2.4rem, 5vw, 4.9rem);
            line-height: 0.95;
            letter-spacing: -0.05em;
        }
        .title-wrap p {
            margin: 12px 0 0;
            color: var(--muted);
            font-size: clamp(1rem, 2vw, 1.2rem);
        }
        .footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
        }
        .footer-card {
            padding: 14px 18px;
            border-radius: 22px;
            backdrop-filter: blur(18px);
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--line);
        }
        .footer-card strong,
        .footer-card span {
            display: block;
        }
        .footer-card span {
            margin-top: 6px;
            color: var(--muted);
        }
        .dots {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: rgba(255,255,255,0.35);
            transition: transform 0.3s ease, background 0.3s ease;
        }
        .dot.active {
            transform: scale(1.7);
            background: #ffffff;
        }
        @keyframes drift {
            from { transform: translate3d(-4%, -3%, 0) scale(1); }
            to { transform: translate3d(4%, 3%, 0) scale(1.08); }
        }
        @media (max-width: 720px) {
            .frame { padding: 10px; }
            .story { aspect-ratio: auto; min-height: 82vh; border-radius: 26px; }
            .footer { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <div class="ambient"></div>
    <div class="frame">
        <div class="story" id="story">
            <div class="overlay"></div>
            <div class="hero">
                <div class="eyebrow">Souvenirs vivants {$place}</div>
                <div class="title-wrap">
                    <h1>{$title}</h1>
                    <p>{$date} · Recap regenere automatiquement a chaque nouveau media.</p>
                </div>
                <div class="footer">
                    <div class="footer-card" id="authorCard">
                        <strong>Dernier souvenir ajoute</strong>
                        <span>Le montage se met a jour en temps reel.</span>
                    </div>
                    <div class="dots" id="dots"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const slides = {$payload} || [];
        const story = document.getElementById('story');
        const dots = document.getElementById('dots');
        const authorCard = document.getElementById('authorCard');

        if (!slides.length) {
            authorCard.innerHTML = '<strong>Aucun media pour le moment</strong><span>Ajoutez des souvenirs pour lancer le recap.</span>';
        } else {
            slides.forEach((slide, index) => {
                const item = document.createElement('div');
                item.className = 'slide' + (index === 0 ? ' active' : '');

                if (slide.type === 'video') {
                    const video = document.createElement('video');
                    video.src = slide.src;
                    video.autoplay = true;
                    video.muted = true;
                    video.loop = true;
                    video.playsInline = true;
                    item.appendChild(video);
                } else {
                    const image = document.createElement('img');
                    image.src = slide.src;
                    image.alt = 'Souvenir';
                    item.appendChild(image);
                }

                story.insertBefore(item, story.firstChild);

                const dot = document.createElement('span');
                dot.className = 'dot' + (index === 0 ? ' active' : '');
                dots.appendChild(dot);
            });

            const renderedSlides = Array.from(document.querySelectorAll('.slide')).reverse();
            const renderedDots = Array.from(document.querySelectorAll('.dot'));
            let current = 0;

            const syncMeta = () => {
                const entry = slides[current];
                const author = entry.author || 'Participant du groupe';
                authorCard.innerHTML = '<strong>' + author + '</strong><span>' + (entry.type === 'video' ? 'Sequence video ajoutee au montage' : 'Photo integree au montage') + '.</span>';
            };

            const showSlide = (index) => {
                renderedSlides.forEach((slide, i) => slide.classList.toggle('active', i === index));
                renderedDots.forEach((dot, i) => dot.classList.toggle('active', i === index));
                current = index;
                syncMeta();
            };

            syncMeta();
            window.setInterval(() => {
                showSlide((current + 1) % renderedSlides.length);
            }, 4200);
        }
    </script>
</body>
</html>
HTML;
    }
}
