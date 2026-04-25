<?php
/**
 * includes/size_recommender.php
 * SizeRecommender — returns recommended size based on bust/waist/hip in cm.
 * Uses Indian standard sizing charts for Kurtis, Dresses, and Co-ord Sets.
 */
declare(strict_types=1);

class SizeRecommender
{
    /**
     * Size charts: [size_label => [bust_max, waist_max, hip_max]]
     * "max" means the measurement fits up to and including this value.
     * All measurements in centimetres.
     */
    private const CHARTS = [
        'Kurtis' => [
            'XS'  => ['bust' => 80,  'waist' => 62,  'hip' => 84],
            'S'   => ['bust' => 84,  'waist' => 66,  'hip' => 88],
            'M'   => ['bust' => 88,  'waist' => 70,  'hip' => 92],
            'L'   => ['bust' => 92,  'waist' => 74,  'hip' => 96],
            'XL'  => ['bust' => 96,  'waist' => 78,  'hip' => 100],
            'XXL' => ['bust' => 104, 'waist' => 86,  'hip' => 108],
        ],
        'Dresses' => [
            'XS'  => ['bust' => 79,  'waist' => 61,  'hip' => 83],
            'S'   => ['bust' => 83,  'waist' => 65,  'hip' => 87],
            'M'   => ['bust' => 87,  'waist' => 69,  'hip' => 91],
            'L'   => ['bust' => 91,  'waist' => 73,  'hip' => 95],
            'XL'  => ['bust' => 95,  'waist' => 77,  'hip' => 99],
            'XXL' => ['bust' => 103, 'waist' => 85,  'hip' => 107],
        ],
        'Co-ord Sets' => [
            'XS'  => ['bust' => 80,  'waist' => 62,  'hip' => 84],
            'S'   => ['bust' => 84,  'waist' => 66,  'hip' => 88],
            'M'   => ['bust' => 88,  'waist' => 70,  'hip' => 92],
            'L'   => ['bust' => 92,  'waist' => 74,  'hip' => 96],
            'XL'  => ['bust' => 96,  'waist' => 78,  'hip' => 100],
            'XXL' => ['bust' => 104, 'waist' => 86,  'hip' => 108],
        ],
        'general' => [
            'XS'  => ['bust' => 80,  'waist' => 62,  'hip' => 84],
            'S'   => ['bust' => 84,  'waist' => 66,  'hip' => 88],
            'M'   => ['bust' => 88,  'waist' => 70,  'hip' => 92],
            'L'   => ['bust' => 92,  'waist' => 74,  'hip' => 96],
            'XL'  => ['bust' => 96,  'waist' => 78,  'hip' => 100],
            'XXL' => ['bust' => 104, 'waist' => 86,  'hip' => 108],
        ],
    ];

    /**
     * Recommend a size based on measurements.
     *
     * @param array  $measurements ['bust' => float, 'waist' => float, 'hip' => float]
     * @param string $category     e.g. 'Dresses', 'Kurtis', 'Co-ord Sets', 'general'
     * @return string e.g. 'M'
     */
    public function recommend(array $measurements, string $category = 'general'): string
    {
        $bust  = (float)($measurements['bust']  ?? 0);
        $waist = (float)($measurements['waist'] ?? 0);
        $hip   = (float)($measurements['hip']   ?? 0);

        if ($bust <= 0 && $waist <= 0 && $hip <= 0) {
            return 'M'; // default fallback
        }

        $chart = self::CHARTS[$category] ?? self::CHARTS['general'];

        foreach ($chart as $size => $maxes) {
            $bustFits  = $bust  <= 0 || $bust  <= $maxes['bust'];
            $waistFits = $waist <= 0 || $waist <= $maxes['waist'];
            $hipFits   = $hip   <= 0 || $hip   <= $maxes['hip'];

            if ($bustFits && $waistFits && $hipFits) {
                return $size;
            }
        }

        return 'XXL'; // largest size if all maxes exceeded
    }

    /**
     * Return the full size chart for display.
     *
     * @param string $category
     * @return array  [ ['size' => 'XS', 'bust' => 80, 'waist' => 62, 'hip' => 84], ... ]
     */
    public function getSizeChart(string $category = 'general'): array
    {
        $chart  = self::CHARTS[$category] ?? self::CHARTS['general'];
        $result = [];
        foreach ($chart as $size => $maxes) {
            $result[] = [
                'size'  => $size,
                'bust'  => $maxes['bust'],
                'waist' => $maxes['waist'],
                'hip'   => $maxes['hip'],
            ];
        }
        return $result;
    }

    /**
     * Return all available categories.
     */
    public function getCategories(): array
    {
        return array_keys(self::CHARTS);
    }

    /**
     * Generate a fit note based on the measurement relative to the chart.
     */
    public function getFitNote(array $measurements, string $size, string $category = 'general'): string
    {
        $chart = self::CHARTS[$category] ?? self::CHARTS['general'];
        if (!isset($chart[$size])) return '';

        $maxes = $chart[$size];
        $notes = [];

        $bust  = (float)($measurements['bust']  ?? 0);
        $waist = (float)($measurements['waist'] ?? 0);
        $hip   = (float)($measurements['hip']   ?? 0);

        if ($bust > 0 && ($maxes['bust'] - $bust) <= 2) $notes[] = 'snug across the bust';
        if ($waist > 0 && ($maxes['waist'] - $waist) <= 2) $notes[] = 'snug at the waist';
        if ($hip > 0 && ($maxes['hip'] - $hip) <= 2) $notes[] = 'snug at the hips';

        if (empty($notes)) {
            return "Size {$size} should be a comfortable fit for your measurements.";
        }
        return "Size {$size} will be " . implode(' and ', $notes) . ". Consider sizing up if you prefer a relaxed fit.";
    }
}
