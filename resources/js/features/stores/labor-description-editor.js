document.addEventListener('alpine:init', () => {
    window.Alpine.data('laborDescriptionEditor', (configuredGroups = []) => ({
        nextKey: 1,
        groups: [],
        init() {
            const source = Array.isArray(configuredGroups) ? configuredGroups : [];
            this.groups = source.map((group) => this.makeGroup(group)).filter((group) => group.label);
            if (this.groups.length === 0) this.groups = [this.makeGroup({ label: 'تضليل' })];
        },
        makeGroup(group = {}) {
            const normalized = typeof group === 'string' ? { label: group, children: [] } : group;

            return {
                key: this.nextKey++,
                label: String(normalized?.label || '').trim(),
                children: (Array.isArray(normalized?.children) ? normalized.children : [])
                    .map((child) => this.makeChild(child))
                    .filter((child) => child.label),
            };
        },
        makeChild(child = {}) {
            const normalized = typeof child === 'string' ? { label: child, type: 'toggle' } : child;
            const type = normalized?.type === 'counter' ? 'counter' : 'toggle';

            return {
                key: this.nextKey++,
                label: String(normalized?.label || '').trim(),
                type,
                max: type === 'counter' ? Math.max(1, Math.min(20, Number(normalized?.max || 4))) : 1,
            };
        },
        addGroup() {
            if (this.groups.length < 8) this.groups.push(this.makeGroup());
        },
        removeGroup(index) {
            if (this.groups.length > 1) this.groups.splice(index, 1);
        },
        addChild(groupIndex) {
            if (this.groups[groupIndex]?.children.length < 12) this.groups[groupIndex].children.push(this.makeChild());
        },
        removeChild(groupIndex, childIndex) {
            this.groups[groupIndex]?.children.splice(childIndex, 1);
        },
    }));
});
