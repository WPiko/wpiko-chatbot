/**
 * Wpiko Chatbot - Models Information Handler
 * Displays detailed model information when users select models in the Responses API section
 * GPT-6 family details checked against OpenAI Models documentation on September 30, 2026
 */

jQuery(document).ready(function ($) {
    console.log('Models Info script loaded');

    // Model information data based on provided table
    const modelsData = {
        'gpt-6-astra': {
            displayName: 'GPT-6 Astra',
            description: 'OpenAI\'s most capable model for complex reasoning, coding, research, and document creation',
            reasoning: 5,
            intelligence: null,
            speed: 3,
            contextWindow: '1,050,000',
            maxOutputTokens: '128,000',
            cost: '$10.00 / $50.00'
        },
        'gpt-6.1-sol': {
            displayName: 'GPT-6.1 Sol',
            description: 'Near-Astra performance for complex coding, computer use, and professional work at a lower cost',
            reasoning: 5,
            intelligence: null,
            speed: 4,
            contextWindow: '1,050,000',
            maxOutputTokens: '128,000',
            cost: '$2.00 / $10.00'
        },
        'gpt-6-sol': {
            displayName: 'GPT-6 Sol',
            description: 'Built for complex coding and agentic workflows',
            reasoning: 5,
            intelligence: null,
            speed: 4,
            contextWindow: '1,050,000',
            maxOutputTokens: '128,000',
            cost: '$2.00 / $10.00'
        },
        'gpt-6-luna': {
            displayName: 'GPT-6 Luna',
            description: 'Efficient model for focused, high-volume tasks',
            reasoning: 3,
            intelligence: null,
            speed: 5,
            contextWindow: '1,050,000',
            maxOutputTokens: '128,000',
            cost: '$0.10 / $0.50'
        },
        'gpt-5.6-sol': {
            displayName: 'GPT-5.6 Sol',
            description: 'Frontier GPT-5.6 model for complex professional work',
            reasoning: 5,
            intelligence: null,
            speed: 3,
            contextWindow: '1,050,000',
            maxOutputTokens: '128,000',
            cost: '$4.00 / $20.00'
        },
        'gpt-5.6-terra': {
            displayName: 'GPT-5.6 Terra',
            description: 'GPT-5.6 model that balances intelligence and cost',
            reasoning: 4,
            intelligence: null,
            speed: 4,
            contextWindow: '1,050,000',
            maxOutputTokens: '128,000',
            cost: '$2.00 / $12.00'
        },
        'gpt-5.6-luna': {
            displayName: 'GPT-5.6 Luna',
            description: 'GPT-5.6 model optimized for cost-sensitive, high-volume workloads',
            reasoning: 3,
            intelligence: null,
            speed: 5,
            contextWindow: '1,050,000',
            maxOutputTokens: '128,000',
            cost: '$0.20 / $1.20'
        },
        'gpt-5.5-2026-04-23': {
            displayName: 'GPT-5.5',
            description: 'Latest flagship GPT-5.5 model for advanced reasoning, coding, and professional workflows',
            reasoning: 5,
            intelligence: null,
            speed: 4,
            contextWindow: '1,050,000',
            maxOutputTokens: '128,000',
            cost: '$5.00 / $30.00'
        },
        'gpt-5.4': {
            displayName: 'GPT-5.4',
            description: 'Most capable frontier model for agentic, coding, and professional workflows',
            reasoning: 5,
            intelligence: null,
            speed: 3,
            contextWindow: '1,050,000',
            maxOutputTokens: '128,000',
            cost: '$2.50 / $15.00'
        },
        'gpt-5.4-mini': {
            displayName: 'GPT-5.4 Mini',
            description: 'Strong mini GPT-5.4 model for coding, computer use, and subagents',
            reasoning: 4,
            intelligence: null,
            speed: 4,
            contextWindow: '400,000',
            maxOutputTokens: '128,000',
            cost: '$0.75 / $4.50'
        },
        'gpt-5.2': {
            displayName: 'GPT-5.2',
            description: 'The best model for coding and agentic tasks across industries',
            reasoning: 4,
            intelligence: null,
            speed: 3,
            contextWindow: '400,000',
            maxOutputTokens: '128,000',
            cost: '$1.75 / $14.00'
        },
        'gpt-4.1': {
            displayName: 'GPT-4.1',
            description: 'Smartest non-reasoning model',
            reasoning: null,
            intelligence: 4,
            speed: 3,
            contextWindow: '1,047,576',
            maxOutputTokens: '32,768',
            cost: '$2.00 / $8.00'
        },
        'gpt-4.1-mini': {
            displayName: 'GPT-4.1 Mini',
            description: 'Smaller, faster version of GPT-4.1',
            reasoning: null,
            intelligence: 3,
            speed: 4,
            contextWindow: '1,047,576',
            maxOutputTokens: '32,768',
            cost: '$0.40 / $1.60'
        }
    };

    /**
     * Generate HTML for model information display
     */
    function generateModelInfoHTML(modelKey) {
        const model = modelsData[modelKey];
        if (!model) {
            return '<div class="wpiko-chatbot-model-info-box"><div class="wpiko-chatbot-info-content"><p>Model information not available.</p></div></div>';
        }

        // Create rating stars function
        function generateStars(rating) {
            if (rating === null) return '<span class="wpiko-rating-na">N/A</span>';
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    stars += '<span class="wpiko-star filled">★</span>';
                } else {
                    stars += '<span class="wpiko-star">★</span>';
                }
            }
            return stars;
        }

        return `
            <div class="wpiko-chatbot-model-info-box wpiko-model-details">
                <div class="wpiko-chatbot-info-icon">
                    <span class="dashicons dashicons-info"></span>
                </div>
                <div class="wpiko-chatbot-info-content">
                    <div class="wpiko-model-header">
                        <h4>${model.displayName}</h4>
                    </div>
                    
                    <div class="wpiko-model-description">
                        <p>${model.description}</p>
                    </div>

                    <div class="wpiko-model-specs">
                        <div class="wpiko-model-spec-grid">
                            ${model.reasoning !== null ? `
                            <div class="wpiko-spec-item">
                                <span class="wpiko-spec-label">Reasoning</span>
                                <span class="wpiko-spec-value">${generateStars(model.reasoning)}</span>
                            </div>
                            ` : ''}
                            ${model.intelligence !== null ? `
                            <div class="wpiko-spec-item">
                                <span class="wpiko-spec-label">Intelligence</span>
                                <span class="wpiko-spec-value">${generateStars(model.intelligence)}</span>
                            </div>
                            ` : ''}
                            ${model.speed !== null ? `
                            <div class="wpiko-spec-item">
                                <span class="wpiko-spec-label">Speed</span>
                                <span class="wpiko-spec-value">${generateStars(model.speed)}</span>
                            </div>
                            ` : ''}
                            <div class="wpiko-spec-item">
                                <span class="wpiko-spec-label">Context Window</span>
                                <span class="wpiko-spec-value">${model.contextWindow} tokens</span>
                            </div>
                            <div class="wpiko-spec-item">
                                <span class="wpiko-spec-label">Max Output</span>
                                <span class="wpiko-spec-value">${model.maxOutputTokens} tokens</span>
                            </div>
                            <div class="wpiko-spec-item">
                                <span class="wpiko-spec-label">Input / Output per 1M</span>
                                <span class="wpiko-spec-value">${model.cost}</span>
                            </div>
                        </div>
                    </div>

                    <div class="wpiko-model-footer">
                        <p class="wpiko-model-footer-text">
                            <span class="dashicons dashicons-external"></span>
                            <a href="https://developers.openai.com/api/docs/models/compare" target="_blank" rel="noopener">
                                View Comparison Table
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Update model information display
     */
    function updateModelInfo(selectedModel) {
        let $modelInfoContainer = $('#wpiko-responses-model-info');

        if (!$modelInfoContainer.length) {
            // Create the container if it doesn't exist
            const $modelSelect = $('#responses_model');
            if ($modelSelect.length) {
                const $parentTd = $modelSelect.closest('td');
                if ($parentTd.length) {
                    $parentTd.append('<div id="wpiko-responses-model-info"></div>');
                    $modelInfoContainer = $('#wpiko-responses-model-info');
                }
            }
        }

        if ($modelInfoContainer.length) {
            const modelInfoHTML = generateModelInfoHTML(selectedModel);
            $modelInfoContainer.html(modelInfoHTML);
        } else {
            console.warn('Wpiko Models Info: Could not find or create model info container');
        }
    }

    /**
     * Initialize model information display
     */
    function initializeModelInfo() {
        const $modelSelect = $('#responses_model');

        if ($modelSelect.length) {
            // Show info for initially selected model
            const initialModel = $modelSelect.val();
            if (initialModel) {
                updateModelInfo(initialModel);
            }

            // Handle model selection changes
            $modelSelect.on('change', function () {
                const selectedModel = $(this).val();
                updateModelInfo(selectedModel);

                // Add a subtle animation
                $('#wpiko-responses-model-info').hide().fadeIn(300);
            });
        }
    }

    /**
     * Get model recommendations based on use case
     */
    function getModelRecommendations() {
        return {
            'cost-effective': ['gpt-6-luna', 'gpt-5.6-luna'],
            'balanced': ['gpt-6.1-sol', 'gpt-5.6-terra'],
            'advanced': ['gpt-6-astra', 'gpt-6.1-sol'],
            'speed': ['gpt-6-luna'],
            'multimodal': ['gpt-4.1', 'gpt-4.1-mini']
        };
    }

    /**
     * Add model comparison feature
     */
    function addModelComparison() {
        // This could be expanded to show a comparison table of all models
        // For now, we'll just expose the function for potential future use
        window.wpikoModelsComparison = function () {
            const models = Object.keys(modelsData);
            console.log('Available models for comparison:', models);
            return modelsData;
        };
    }

    /**
     * Initialize everything when the Responses API section is visible
     */
    function initializeWhenResponsesVisible() {
        const $responsesSection = $('#responses-api-settings');

        if ($responsesSection.is(':visible')) {
            initializeModelInfo();
        }

        // Watch for API type changes
        $('input[name="api_type"]').on('change', function () {
            if ($(this).val() === 'responses' && $(this).is(':checked')) {
                setTimeout(function () {
                    initializeModelInfo();
                }, 100);
            }
        });

        // Watch for save button that shows/hides sections
        $('#save_api_type').on('click', function () {
            setTimeout(function () {
                const $responsesSection = $('#responses-api-settings');
                if ($responsesSection.is(':visible')) {
                    initializeModelInfo();
                }
            }, 500);
        });
    }

    // Initialize when document is ready
    initializeWhenResponsesVisible();
    addModelComparison();

    // Expose modelsData globally for debugging/development
    window.wpikoModelsData = modelsData;

    // Add debug function for console testing
    window.wpikoDebugModels = function () {
        console.log('=== Wpiko Chatbot Models Debug Info ===');
        console.log('Available models:', Object.keys(modelsData));
        console.log('Current selection:', $('#responses_model').val());
        console.log('Responses section visible:', $('#responses-api-settings').is(':visible'));
        console.log('Model info container exists:', $('#wpiko-responses-model-info').length > 0);
        console.log('Full models data:', modelsData);
    };

    // Log successful initialization
    console.log('Wpiko Chatbot Models Info initialized with', Object.keys(modelsData).length, 'models');
    console.log('Available debug commands: wpikoDebugModels(), wpikoModelsComparison()');
});
