import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const args = process.argv.slice(2);
const planOnly = args.includes('--plan');
const positional = args.filter((value) => !value.startsWith('--'));
const origin = positional[0] ?? null;
const outputPath = resolve(positional[1] ?? 'storage/app/pwa-acceptance/latest.md');
const contract = JSON.parse(readFileSync('config/pwa_acceptance.json', 'utf8'));

const commands = [
    ['build', 'npm', ['run', 'build']],
    ['pwa_contract', 'npm', ['run', 'test:pwa']],
    ['phase3_contract', 'npm', ['run', 'test:pwa:phase3']],
    ['outbox_contract', 'npm', ['run', 'test:pwa:outbox']],
    ['operational_controls', 'npm', ['run', 'test:pwa:controls']],
    ['deep_links', 'npm', ['run', 'test:pwa:deep-links']],
    ['notifications', 'npm', ['run', 'test:notifications']],
    ['deployment', 'node', ['scripts/check-pwa-deployment.mjs', origin]],
    ['browser', 'node', ['scripts/check-pwa-browser.mjs', origin]],
];
const manualEnvironment = {
    android_install_update: 'PWA_ACCEPT_ANDROID',
    push_delivery_and_click: 'PWA_ACCEPT_PUSH',
    outbox_disconnect_reconnect: 'PWA_ACCEPT_OUTBOX',
    account_switch_isolation: 'PWA_ACCEPT_ACCOUNT_ISOLATION',
    kill_switch_rollback: 'PWA_ACCEPT_ROLLBACK',
};

const validateContract = () => {
    if (contract.schema_version !== 1) throw new Error('Unsupported PWA acceptance schema.');
    const commandNames = new Set(commands.map(([name]) => name));
    for (const gate of contract.required_automated_gates) {
        if (!commandNames.has(gate)) throw new Error(`Missing automated gate command: ${gate}`);
    }
    for (const gate of Object.keys(contract.required_manual_gates)) {
        if (!manualEnvironment[gate]) throw new Error(`Missing manual gate environment mapping: ${gate}`);
    }
};

const run = (command, commandArgs) => new Promise((resolvePromise) => {
    const child = spawn(command, commandArgs, { cwd: process.cwd(), env: process.env });
    let output = '';
    child.stdout.on('data', (chunk) => { output += chunk; process.stdout.write(chunk); });
    child.stderr.on('data', (chunk) => { output += chunk; process.stderr.write(chunk); });
    child.on('error', (error) => resolvePromise({ exitCode: 1, output: `${output}\n${error.message}`.trim() }));
    child.on('close', (exitCode) => resolvePromise({ exitCode: exitCode ?? 1, output: output.trim() }));
});

validateContract();
if (planOnly) {
    console.log(`PWA staging acceptance plan is valid (${commands.length} automated, ${Object.keys(manualEnvironment).length} manual gates).`);
    process.exit(0);
}
if (!origin) {
    console.error('Usage: node scripts/run-pwa-staging-acceptance.mjs https://staging.example.com [report.md]');
    process.exit(2);
}
const candidate = new URL(origin);
if (candidate.protocol !== 'https:' && !['localhost', '127.0.0.1'].includes(candidate.hostname)) {
    console.error('Staging acceptance requires HTTPS except on localhost.');
    process.exit(2);
}

const startedAt = new Date();
const automated = [];
for (const [name, command, commandArgs] of commands) {
    console.log(`\n=== ${name} ===`);
    const result = await run(command, commandArgs);
    automated.push({ name, status: result.exitCode === 0 ? 'PASS' : 'FAIL', ...result });
    if (result.exitCode !== 0) break;
}
for (const required of contract.required_automated_gates) {
    if (!automated.some(({ name }) => name === required)) {
        automated.push({ name: required, status: 'BLOCKED', exitCode: null, output: 'Blocked by an earlier failed automated gate.' });
    }
}

const manual = Object.entries(contract.required_manual_gates).map(([name, description]) => {
    const environment = manualEnvironment[name];
    return {
        name,
        description,
        environment,
        status: process.env[environment] === 'pass' ? 'PASS' : 'PENDING',
    };
});
const sloEvidence = contract.provisional_slo_evidence.map((name) => ({
    name,
    status: process.env[`PWA_SLO_${name.toUpperCase()}`] === 'pass' ? 'PASS' : 'PENDING',
}));
const go = automated.every(({ status }) => status === 'PASS')
    && manual.every(({ status }) => status === 'PASS')
    && sloEvidence.every(({ status }) => status === 'PASS');

const rows = (items, label = 'Gate') => items.map((item) => `| ${item.name} | ${item.status} | ${item.description ?? ''} |`).join('\n');
const automatedLogs = automated.map(({ name, output }) => `### ${name}\n\n\`\`\`text\n${(output || 'No output.').replaceAll('\`\`\`', '\`\` \`')}\n\`\`\``).join('\n\n');
const report = `# PWA Staging Acceptance Report\n\n`
    + `- Origin: \`${candidate.origin}\`\n`
    + `- Started: ${startedAt.toISOString()}\n`
    + `- Finished: ${new Date().toISOString()}\n`
    + `- Decision: **${go ? 'GO' : 'NO-GO'}**\n\n`
    + `## Automated gates\n\n| Gate | Status | Notes |\n| --- | --- | --- |\n${rows(automated)}\n\n`
    + `## Automated logs\n\n${automatedLogs}\n\n`
    + `## Manual evidence\n\n| Gate | Status | Requirement |\n| --- | --- | --- |\n${rows(manual)}\n\n`
    + `## SLO evidence\n\n| Evidence | Status | Notes |\n| --- | --- | --- |\n${rows(sloEvidence)}\n\n`
    + `## Rule\n\nGO requires every automated, manual, and SLO gate to be PASS. PENDING and BLOCKED never count as success.\n`;
mkdirSync(dirname(outputPath), { recursive: true });
writeFileSync(outputPath, report);
console.log(`\nAcceptance report written to ${outputPath}`);
console.log(`Decision: ${go ? 'GO' : 'NO-GO'}`);
process.exit(go ? 0 : 1);
