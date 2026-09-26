import fs from 'node:fs';

const roadmap = fs.readFileSync('docs/خارطة-طريق-PWA-Flutter.md', 'utf8');
const scope = fs.readFileSync('docs/roadmap-current-scope.md', 'utf8');
const checks = [
    ['roadmap declares PWA as the active track', roadmap.includes('**المسار النشط:** إكمال PWA')],
    ['roadmap explicitly defers Flutter', roadmap.includes('# المرحلة 8: Flutter MVP — مؤجلة بقرار النطاق') && roadmap.includes('**الحالة: Deferred.**')],
    ['PWA release is independent from Flutter', roadmap.includes('لا تعد هذه المرحلة شرطًا لإطلاق PWA')],
    ['execution update matches the current outbox', roadmap.includes('Outbox محدود لمسودة الجرد فقط')],
    ['scope forbids Flutter project artifacts for now', scope.includes('لا ينشأ حاليًا `pubspec.yaml`') && scope.includes('أو ملف Dart')],
    ['scope preserves Laravel as business source of truth', scope.includes('خدمات Laravel هي المصدر الوحيد لمنطق العمل')],
    ['scope defines an operational PWA completion gate', scope.includes('## تعريف اكتمال PWA') && scope.includes('ينجح Pilot محدود دون فروقات بيانات')],
    ['offline financial and inventory approvals remain excluded', scope.includes('أي Offline لعملية مالية أو اعتماد مخزني')],
];

for (const [label, passed] of checks) {
    if (!passed) throw new Error(`FAIL: ${label}`);
    console.log(`PASS: ${label}`);
}

console.log(`Roadmap scope contract passed (${checks.length} checks).`);
