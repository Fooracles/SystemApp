import React, { useState, useMemo } from 'react';

// Mock team data with roles and dependencies
const MOCK_TEAM = [
  { id: 'admin-1', name: 'John Smith', role: 'admin', email: 'john@company.com', avatar: 'JS', reportsTo: null },
  { id: 'manager-1', name: 'Sarah Johnson', role: 'manager', email: 'sarah@company.com', avatar: 'SJ', reportsTo: 'admin-1' },
  { id: 'manager-2', name: 'Mike Chen', role: 'manager', email: 'mike@company.com', avatar: 'MC', reportsTo: 'admin-1' },
  { id: 'doer-1', name: 'Emily Davis', role: 'doer', email: 'emily@company.com', avatar: 'ED', reportsTo: 'manager-1' },
  { id: 'doer-2', name: 'Alex Turner', role: 'doer', email: 'alex@company.com', avatar: 'AT', reportsTo: 'manager-1' },
  { id: 'doer-3', name: 'Lisa Wong', role: 'doer', email: 'lisa@company.com', avatar: 'LW', reportsTo: 'manager-2' },
  { id: 'doer-4', name: 'David Brown', role: 'doer', email: 'david@company.com', avatar: 'DB', reportsTo: 'manager-2' },
  { id: 'client-1', name: 'Acme Corp', role: 'client', email: 'contact@acme.com', avatar: 'AC', reportsTo: 'manager-1' },
  { id: 'client-2', name: 'TechStart Inc', role: 'client', email: 'hello@techstart.com', avatar: 'TI', reportsTo: 'manager-2' },
];

const ROLE_COLORS = {
  admin: { bg: '#dc2626', text: '#fecaca', label: 'Admin' },
  manager: { bg: '#2563eb', text: '#bfdbfe', label: 'Manager' },
  doer: { bg: '#6366f1', text: '#c7d2fe', label: 'Doer' },
  client: { bg: '#8b5cf6', text: '#e9d5ff', label: 'Client' },
};

const styles = {
  container: {
    padding: 24,
    height: '100%',
    overflow: 'auto',
    background: '#0f0f14',
  },
  header: {
    marginBottom: 24,
  },
  title: {
    color: '#f4f4f5',
    fontSize: 22,
    fontWeight: 600,
    margin: 0,
  },
  subtitle: {
    color: '#71717a',
    fontSize: 13,
    marginTop: 4,
  },
  legend: {
    display: 'flex',
    gap: 16,
    marginBottom: 24,
    flexWrap: 'wrap',
  },
  legendItem: {
    display: 'flex',
    alignItems: 'center',
    gap: 8,
  },
  legendDot: {
    width: 12,
    height: 12,
    borderRadius: '50%',
  },
  legendLabel: {
    fontSize: 12,
    color: '#a1a1aa',
  },
  orgChart: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    gap: 40,
    paddingTop: 20,
  },
  level: {
    display: 'flex',
    justifyContent: 'center',
    gap: 24,
    flexWrap: 'wrap',
    position: 'relative',
  },
  memberCard: {
    background: '#18181b',
    border: '1px solid #2a2a35',
    borderRadius: 12,
    padding: 16,
    minWidth: 180,
    textAlign: 'center',
    position: 'relative',
    transition: 'transform 0.15s, box-shadow 0.15s',
  },
  memberCardHover: {
    transform: 'translateY(-2px)',
    boxShadow: '0 8px 24px rgba(0,0,0,0.4)',
  },
  avatar: {
    width: 48,
    height: 48,
    borderRadius: '50%',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: 16,
    fontWeight: 600,
    margin: '0 auto 12px',
  },
  memberName: {
    fontSize: 14,
    fontWeight: 600,
    color: '#f4f4f5',
    marginBottom: 4,
  },
  memberEmail: {
    fontSize: 11,
    color: '#71717a',
    marginBottom: 8,
  },
  roleBadge: {
    display: 'inline-block',
    padding: '3px 10px',
    borderRadius: 20,
    fontSize: 10,
    fontWeight: 600,
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
  },
  connector: {
    position: 'absolute',
    top: -20,
    left: '50%',
    width: 2,
    height: 20,
    background: '#3f3f46',
  },
  statsBar: {
    display: 'flex',
    gap: 16,
    marginBottom: 24,
    flexWrap: 'wrap',
  },
  statCard: {
    background: '#18181b',
    border: '1px solid #2a2a35',
    borderRadius: 10,
    padding: '12px 20px',
    minWidth: 120,
    textAlign: 'center',
  },
  statValue: {
    fontSize: 24,
    fontWeight: 700,
    color: '#f4f4f5',
  },
  statLabel: {
    fontSize: 11,
    color: '#71717a',
    marginTop: 2,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: 600,
    color: '#a1a1aa',
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
    marginBottom: 16,
    textAlign: 'center',
  },
  levelContainer: {
    width: '100%',
  },
  connectionLine: {
    position: 'absolute',
    top: -40,
    left: '50%',
    transform: 'translateX(-50%)',
    width: 2,
    height: 40,
    background: '#3f3f46',
  },
  horizontalLine: {
    position: 'absolute',
    top: -40,
    height: 2,
    background: '#3f3f46',
  },
};

function MemberCard({ member, showConnector }) {
  const [hover, setHover] = useState(false);
  const roleColor = ROLE_COLORS[member.role] || ROLE_COLORS.doer;

  return (
    <div
      style={{
        ...styles.memberCard,
        ...(hover ? styles.memberCardHover : {}),
        borderColor: hover ? roleColor.bg : '#2a2a35',
      }}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
    >
      {showConnector && <div style={styles.connector} />}
      <div style={{ ...styles.avatar, background: roleColor.bg, color: roleColor.text }}>
        {member.avatar}
      </div>
      <div style={styles.memberName}>{member.name}</div>
      <div style={styles.memberEmail}>{member.email}</div>
      <span style={{ ...styles.roleBadge, background: `${roleColor.bg}22`, color: roleColor.bg }}>
        {roleColor.label}
      </span>
    </div>
  );
}

export function TeamView() {
  const hierarchy = useMemo(() => {
    const admins = MOCK_TEAM.filter((m) => m.role === 'admin');
    const managers = MOCK_TEAM.filter((m) => m.role === 'manager');
    const doers = MOCK_TEAM.filter((m) => m.role === 'doer');
    const clients = MOCK_TEAM.filter((m) => m.role === 'client');
    return { admins, managers, doers, clients };
  }, []);

  const stats = useMemo(() => {
    return Object.entries(ROLE_COLORS).map(([role, config]) => ({
      role,
      label: config.label + 's',
      count: MOCK_TEAM.filter((m) => m.role === role).length,
      color: config.bg,
    }));
  }, []);

  return (
    <div style={styles.container}>
      <div style={styles.header}>
        <h1 style={styles.title}>Team Structure</h1>
        <p style={styles.subtitle}>
          Organizational hierarchy showing reporting relationships and dependencies
        </p>
      </div>

      {/* Stats */}
      <div style={styles.statsBar}>
        {stats.map((stat) => (
          <div key={stat.role} style={styles.statCard}>
            <div style={{ ...styles.statValue, color: stat.color }}>{stat.count}</div>
            <div style={styles.statLabel}>{stat.label}</div>
          </div>
        ))}
        <div style={styles.statCard}>
          <div style={styles.statValue}>{MOCK_TEAM.length}</div>
          <div style={styles.statLabel}>Total Members</div>
        </div>
      </div>

      {/* Legend */}
      <div style={styles.legend}>
        {Object.entries(ROLE_COLORS).map(([role, config]) => (
          <div key={role} style={styles.legendItem}>
            <div style={{ ...styles.legendDot, background: config.bg }} />
            <span style={styles.legendLabel}>{config.label}</span>
          </div>
        ))}
      </div>

      {/* Org Chart */}
      <div style={styles.orgChart}>
        {/* Admin Level */}
        {hierarchy.admins.length > 0 && (
          <div style={styles.levelContainer}>
            <div style={styles.sectionTitle}>Administration</div>
            <div style={styles.level}>
              {hierarchy.admins.map((member) => (
                <MemberCard key={member.id} member={member} showConnector={false} />
              ))}
            </div>
          </div>
        )}

        {/* Manager Level */}
        {hierarchy.managers.length > 0 && (
          <div style={styles.levelContainer}>
            <div style={styles.sectionTitle}>Management</div>
            <div style={styles.level}>
              {hierarchy.managers.map((member) => (
                <MemberCard key={member.id} member={member} showConnector />
              ))}
            </div>
          </div>
        )}

        {/* Doer Level */}
        {hierarchy.doers.length > 0 && (
          <div style={styles.levelContainer}>
            <div style={styles.sectionTitle}>Team Members</div>
            <div style={styles.level}>
              {hierarchy.doers.map((member) => (
                <MemberCard key={member.id} member={member} showConnector />
              ))}
            </div>
          </div>
        )}

        {/* Client Level */}
        {hierarchy.clients.length > 0 && (
          <div style={styles.levelContainer}>
            <div style={styles.sectionTitle}>Clients</div>
            <div style={styles.level}>
              {hierarchy.clients.map((member) => (
                <MemberCard key={member.id} member={member} showConnector />
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
