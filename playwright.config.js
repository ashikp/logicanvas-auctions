const { defineConfig, test, expect } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './tests/E2E',
	use: { baseURL: process.env.WCAP_BASE_URL || 'http://localhost' },
});
