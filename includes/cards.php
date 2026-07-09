<?php
/**
 * Purble Pairs — Card pool and difficulty configuration
 * Shared by game.php and endless.php
 */

$allEmojis = [
    '🚗','🐶','🍎','🌟','🎵','🏆','🌈','🦋','🎸','🍕','🐱','🚀',
    '🦊','🐸','🍇','🍓','🌸','🔥','💎','🎯','🦄','🐙','🍦','🎃',
    '🐼','🦁','🐯','🦀','🦞','🦜','🌵','🍄','🧁','🎲','🧲','🪄',
    '🦈','🐬','🦩','🪸',
];

function getBeginnerConfig(): array {
    return ['cols' => 4, 'rows' => 3, 'time' => 180, 'label' => 'Beginner',     'pts' => 5];
}

function getIntermediateConfig(): array {
    return ['cols' => 4, 'rows' => 4, 'time' => 120, 'label' => 'Intermediate', 'pts' => 5];
}

function getAdvancedConfig(): array {
    return ['cols' => 6, 'rows' => 4, 'time' => 90,  'label' => 'Advanced',     'pts' => 5];
}

function getAsianConfig(): array {
    return ['cols' => 10, 'rows' => 8, 'time' => 90, 'label' => 'Asian',        'pts' => 10];
}

function getDifficultyConfig(string $difficulty): array {
    return match($difficulty) {
        'intermediate' => getIntermediateConfig(),
        'advanced'     => getAdvancedConfig(),
        'asian'        => getAsianConfig(),
        default        => getBeginnerConfig(),
    };
}

function isValidDifficulty(string $difficulty): bool {
    return in_array($difficulty, ['beginner', 'intermediate', 'advanced', 'asian']);
}

function renderCardGrid(array $cards, int $cols): void {
    echo "<div id=\"grid\" class=\"grid gap-3 w-full max-w-2xl\""
       . " style=\"grid-template-columns: repeat({$cols}, minmax(0, 1fr));\">";

    foreach ($cards as $index => $emoji) {
        echo "<button class=\"perspective w-full aspect-square\""
           . " data-id=\"{$index}\""
           . " onclick=\"flipCard({$index})\">";
        echo "<div class=\"card-inner w-full h-full rounded-xl\" id=\"card-{$index}\">";

        echo '<div class="card-face card-back-face w-full h-full rounded-xl bg-purple-600'
           . ' flex items-center justify-center shadow-md border-2 border-purple-400'
           . ' cursor-pointer hover:shadow-lg transition-shadow">'
           . '<span class="text-3xl md:text-4xl opacity-60 select-none">❓</span>'
           . '</div>';

        echo '<div class="card-face card-front-face w-full h-full rounded-xl bg-white'
           . ' flex items-center justify-center shadow-md border-2 border-gray-200">'
           . "<span class=\"text-3xl md:text-5xl select-none\">{$emoji}</span>"
           . '</div>';

        echo '</div>';
        echo '</button>';
    }

    echo '</div>';
}
