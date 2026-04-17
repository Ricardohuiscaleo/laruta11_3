/**
 * AWS Bedrock client for Amazon Nova Micro.
 * Uses the Converse API to send messages and receive structured responses.
 * Includes retry logic: 2 retries with exponential backoff (1s, 3s).
 */

const {
  BedrockRuntimeClient,
  ConverseCommand,
} = require('@aws-sdk/client-bedrock-runtime');
const config = require('../config');

const MODEL_ID = 'amazon.nova-micro-v1:0';
const MAX_RETRIES = 2;
const BASE_DELAY_MS = 1000;

const client = new BedrockRuntimeClient({ region: config.AWS_REGION });

/**
 * Sleep helper for exponential backoff.
 * @param {number} ms
 * @returns {Promise<void>}
 */
function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Send a message to Amazon Nova Micro via the Converse API.
 *
 * @param {string} systemPrompt - System-level instructions for the model.
 * @param {string} userMessage  - The user's natural-language message.
 * @returns {Promise<string>} The raw text content from the model response.
 */
async function invoke(systemPrompt, userMessage) {
  const input = {
    modelId: MODEL_ID,
    system: [{ text: systemPrompt }],
    messages: [
      {
        role: 'user',
        content: [{ text: userMessage }],
      },
    ],
    inferenceConfig: {
      maxTokens: 1024,
      temperature: 0.1,
    },
  };

  let lastError;

  for (let attempt = 0; attempt <= MAX_RETRIES; attempt++) {
    try {
      const command = new ConverseCommand(input);
      const response = await client.send(command);

      const outputMessage = response.output?.message;
      if (!outputMessage || !outputMessage.content || outputMessage.content.length === 0) {
        throw new Error('Respuesta vacía del modelo');
      }

      return outputMessage.content[0].text;
    } catch (err) {
      lastError = err;

      if (attempt < MAX_RETRIES) {
        const delay = BASE_DELAY_MS * Math.pow(3, attempt); // 1s, 3s
        console.warn(
          `[BedrockClient] Intento ${attempt + 1} falló: ${err.message}. Reintentando en ${delay}ms…`
        );
        await sleep(delay);
      }
    }
  }

  throw lastError;
}

module.exports = { invoke, MODEL_ID, MAX_RETRIES, BASE_DELAY_MS };
