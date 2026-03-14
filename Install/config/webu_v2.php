<?php

return [
    'flags' => [
        'code_first_initial_generation' => env('WEBU_V2_CODE_FIRST_INITIAL_GENERATION', true),
        'workspace_backed_visual_builder' => env('WEBU_V2_WORKSPACE_BACKED_VISUAL_BUILDER', true),
        'image_to_site_import' => env('WEBU_V2_IMAGE_TO_SITE_IMPORT', true),
        'advanced_ai_workspace_edits' => env('WEBU_V2_ADVANCED_AI_WORKSPACE_EDITS', true),
    ],
];
