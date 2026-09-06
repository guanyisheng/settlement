<?php

declare(strict_types=1);

/** 毛照 / 荣誉等图片上传限制（单次请求合计） */
class UploadLimits
{
    /** 单次多选上传合计上限 30MB（nginx/php 已开到 50MB，应用层卡 30） */
    public const BATCH_MAX_BYTES = 30 * 1024 * 1024;

    /** 单张上限（仍需小于合计 30MB） */
    public const FILE_MAX_BYTES = 15 * 1024 * 1024;

    public const BATCH_MAX_LABEL = '30MB';
    public const FILE_MAX_LABEL = '15MB';

    public static function assertBatchSize(array $files): void
    {
        $total = 0;
        foreach ($files as $file) {
            $total += (int) ($file['size'] ?? 0);
        }
        if ($total > self::BATCH_MAX_BYTES) {
            $mb = round($total / 1024 / 1024, 1);
            throw new InvalidArgumentException(
                '一次最多上传 ' . self::BATCH_MAX_LABEL . '（当前约 ' . $mb . 'MB），请减少图片数量或压缩后重试'
            );
        }
    }

    public static function hint(): string
    {
        return '可多选图片；单次合计不超过 ' . self::BATCH_MAX_LABEL . '，单张不超过 ' . self::FILE_MAX_LABEL . '；支持 JPG/PNG/WEBP';
    }
}
