import { readFileSync } from 'node:fs';

const agents = readFileSync('AGENTS.md', 'utf8');
const policy = readFileSync('docs/قاعدة-PWA-لكل-تغيير.md', 'utf8');
const failures = [];

const requiredAgentRules = [
    'قاعدة PWA الإلزامية لكل تغيير',
    'docs/قاعدة-PWA-لكل-تغيير.md',
    'لا ينطبق',
    'Idempotency',
    'npm run test:pwa:change-policy',
];
const requiredPolicyRules = [
    'كل إضافة أو تعديل أو إصلاح',
    'Online-only',
    'Service Worker',
    'تبديل الحساب',
    'npm run test:pwa',
    'PENDING',
];

for (const rule of requiredAgentRules) {
    if (!agents.includes(rule)) failures.push(`AGENTS.md is missing: ${rule}`);
}
for (const rule of requiredPolicyRules) {
    if (!policy.includes(rule)) failures.push(`PWA change policy is missing: ${rule}`);
}

if (failures.length) {
    failures.forEach((failure) => console.error(`FAIL: ${failure}`));
    process.exit(1);
}

console.log('PWA change policy passed: every change must record and verify its PWA impact.');
