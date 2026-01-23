# AIServer Split Architecture Plan

## 📋 Overview

Rencana untuk memisahkan **AIServer.exe** ke server terpisah untuk offload CPU dari main game server.

---

## 🏗️ Architecture

### **Current (All-in-One)**

```
┌─────────────────────────────────────────┐
│   Single Server - Contabo SG ($20/mo)  │
│   ─────────────────────────────────     │
│                                          │
│   • MapID.exe (Players)                 │
│   • DBServer.exe (Database proxy)       │
│   • AIServer.exe (Monster AI) ← CPU!    │
│   • LogServer.exe (Logging)             │
│   • SQL Server (Database)               │
│                                          │
│   CPU: 8 vCPU                            │
│   RAM: 30GB                              │
└─────────────────────────────────────────┘
```

### **Proposed (Split AI)**

```
┌────────────────────────────────┐  ┌───────────────────────────────┐
│  Main - Contabo SG ($20/mo)   │  │  AI - Oracle SG (FREE/Cheap)  │
│  ────────────────────────────  │  │  ───────────────────────────  │
│                                 │  │                               │
│  • MapID.exe                   │  │  • AIServer.exe               │
│  • DBServer.exe :22500         │  │                               │
│  • LogServer.exe               │  │  Handles:                     │
│  • SQL Server                  │  │  - Monster AI                 │
│  • Laravel Auth                │  │  - NPC spawning               │
│                                 │  │  - Mob behavior               │
│  Handles:                      │  │                               │
│  - Player connections          │  │  CPU: ARM-based (efficient)   │
│  - Combat                      │  │  RAM: 24GB                    │
│  - Items                       │  │                               │
│                                 │  │  Connect to:                  │
│  CPU: 8 vCPU (x86)             │  │  DBServer:22500 ──────────────┼─┐
│  RAM: 30GB                     │  │                               │ │
└─────────────────────────────────┘  └───────────────────────────────┘ │
         ▲                                                              │
         └──────────────────────────────────────────────────────────────┘
         
Network: Singapore <-> Singapore (2-5ms latency)
Communication: DCOM (Distributed COM)
```

---

## 🔧 Technical Details

### **DCOM = Distributed Component Object Model**

Windows mechanism untuk memanggil fungsi di komputer lain via network.

**Current (Local COM):**
```
MapID.exe → Call function → AIServer.dll (same computer)
Speed: < 1ms
```

**After Split (DCOM):**
```
MapID.exe → Call function → AIServer.exe (different computer)
Speed: 2-5ms (Singapore to Singapore)
```

---

## ⚙️ Configuration Requirements

### **1. Server A (Main - Contabo)**

**Registry Changes:**
```registry
[HKEY_LOCAL_MACHINE\SOFTWARE\WOW6432Node\Geomind\Gamenet\MAP]
"ServerClassAI"="FAIRY.ROHAN_4.MAP_AI.ROHAN_4@<oracle-sg-ip>"
```

**Firewall:**
```powershell
# Allow outbound DCOM
New-NetFirewallRule -DisplayName "DCOM to AI Server" `
    -Direction Outbound `
    -Protocol TCP `
    -RemoteAddress <oracle-sg-ip> `
    -RemotePort 135,1024-65535 `
    -Action Allow
```

---

### **2. Server B (AI - Oracle)**

**Enable DCOM:**
```powershell
# Run dcomcnfg
# Component Services → Computers → My Computer → Properties
# ✅ Enable Distributed COM on this computer
# Default Authentication Level: Connect
# Default Impersonation Level: Identify
```

**Firewall Rules:**
```powershell
# Allow DCOM from Main server
New-NetFirewallRule -DisplayName "DCOM AIServer Inbound" `
    -Direction Inbound `
    -Protocol TCP `
    -LocalPort 135,1024-65535 `
    -RemoteAddress <contabo-sg-ip> `
    -Action Allow

# Allow connection to DBServer
New-NetFirewallRule -DisplayName "DBServer Connection" `
    -Direction Outbound `
    -Protocol TCP `
    -RemoteAddress <contabo-sg-ip> `
    -RemotePort 22500 `
    -Action Allow
```

**Registry:**
```registry
[HKEY_LOCAL_MACHINE\SOFTWARE\WOW6432Node\Geomind\Gamenet\AI]
"DBServer"="<contabo-sg-ip>:22500"
"ServerClass"="FAIRY.ROHAN_4.AI.ROHAN_4_1"
"Module"="C:\\RohanServer\\Fairy\\AIServer.exe"
```

---

## 📊 Cost Comparison

| Setup | Monthly Cost | CPU Available | Complexity | Performance |
|-------|--------------|---------------|------------|-------------|
| **All-in-one Contabo L** | $20 | 8 vCPU | 🟢 Simple | 🟡 Medium |
| **All-in-one Contabo XL** | $28 | 10 vCPU | 🟢 Simple | 🟢 High |
| **Split: Contabo L + Oracle FREE** | $20 | 8 + 4 vCPU | 🟡 Medium | 🟢 High |
| **Split: Contabo L + Oracle Paid** | $33 | 8 + 4 vCPU | 🟡 Medium | 🟢 Very High |

---

## ✅ Pros

| Benefit | Impact |
|---------|--------|
| **Cost Savings** | Use Oracle Free Tier ($0) or cheap ARM ($13) |
| **CPU Offload** | MapID gets full 8 vCPU for players |
| **Scalability** | Can add more AI servers independently |
| **ARM Efficiency** | Oracle Ampere ARM very efficient for AI workload |
| **Low Latency** | 2-5ms (Singapore to Singapore) |

---

## ⚠️ Cons

| Challenge | Severity | Mitigation |
|-----------|----------|------------|
| **DCOM Configuration** | 🟡 Medium | Follow documented steps |
| **Network Dependency** | 🟡 Medium | Use reliable providers |
| **Firewall Complexity** | 🟡 Medium | Document port requirements |
| **Debugging Difficulty** | 🟡 Medium | Remote debugging tools |
| **Two Servers to Manage** | 🟢 Low | Automation & monitoring |

---

## 🚀 Implementation Plan

### **Phase 1: Preparation (Day 1-2)**

1. **Setup Oracle Cloud Account**
   - Create free tier account
   - Provision Windows Server instance (Singapore region)
   - Configure network security groups

2. **Backup Current Setup**
   - Create snapshot of Contabo server
   - Backup registry settings
   - Document current configuration

---

### **Phase 2: DCOM Configuration (Day 3-4)**

1. **Server B (Oracle) Setup**
   - Install Windows Server
   - Enable DCOM
   - Configure firewall rules
   - Test DCOM connectivity

2. **Server A (Contabo) Setup**
   - Update registry to point to remote AI
   - Configure firewall for outbound DCOM
   - Test connection to Server B

---

### **Phase 3: AIServer Migration (Day 5-6)**

1. **Install AIServer on Oracle**
   - Copy AIServer.exe and dependencies
   - Update registry configuration
   - Point to Contabo DBServer

2. **Test Communication**
   - Start AIServer on Oracle
   - Start MapID on Contabo
   - Verify DCOM communication
   - Check monster spawning & AI behavior

---

### **Phase 4: Testing & Optimization (Day 7-10)**

1. **Performance Testing**
   - Measure latency (should be 2-5ms)
   - Monitor CPU usage on both servers
   - Test with increasing player load

2. **Stability Testing**
   - 24-hour uptime test
   - Network failure scenarios
   - Monster AI behavior validation

---

### **Phase 5: Production Migration (Day 11-12)**

1. **Scheduled Downtime**
   - Announce maintenance window
   - Migrate to split architecture
   - Monitor for 48 hours

2. **Rollback Plan**
   - Keep Contabo snapshot ready
   - Can revert within 1 hour if issues

---

## 🔍 Monitoring & Metrics

### **Key Metrics to Track:**

```
Server A (Contabo - Main):
├─ CPU Usage: Should drop to 40-60% (from 80%+)
├─ RAM Usage: Monitor for leaks
├─ Network Traffic: Monitor DCOM traffic to Oracle
└─ Player Lag: Should improve or stay same

Server B (Oracle - AI):
├─ CPU Usage: Expected 30-50% for AI
├─ RAM Usage: Monitor AI memory usage
├─ Network Latency: Should be 2-5ms to Contabo
└─ Monster AI Response: Should be normal
```

---

## 🎯 Decision Matrix

### **When to Stay All-in-One:**

```
✅ Stay if:
- CPU usage < 70% on Contabo L
- Budget allows Contabo XL upgrade ($28)
- Want simplest setup
- Small-medium player count (< 100 players)
```

### **When to Split:**

```
✅ Split if:
- CPU consistently > 80% on Contabo L
- Budget tight (can't afford XL)
- Player count growing (100-200 players)
- Willing to invest time in setup
```

---

## 📚 Resources

### **DCOM Documentation:**
- Microsoft DCOM Overview: https://docs.microsoft.com/dcom
- DCOM Security: https://docs.microsoft.com/dcom-security

### **Oracle Cloud:**
- Free Tier: https://www.oracle.com/cloud/free/
- Windows on ARM: https://docs.oracle.com/iaas/windows

### **Troubleshooting:**
- DCOM Event Viewer: `Event Viewer → Windows Logs → System`
- DCOM Config: `dcomcnfg.exe`
- Test Connection: `Test-NetConnection -ComputerName <ip> -Port 135`

---

## ⚡ Quick Start (If Decided to Split)

```powershell
# Server B (Oracle) - One-time setup
1. dcomcnfg → Enable DCOM
2. New-NetFirewallRule (see Configuration section)
3. Copy AIServer files
4. Update registry
5. Start AIServer.exe

# Server A (Contabo) - Configuration
1. Update registry: ServerClassAI with @remote-ip
2. Restart MapID.exe
3. Monitor logs

# Verification
1. Check Event Viewer for DCOM errors
2. Test monster spawning in-game
3. Monitor CPU usage on both servers
```

---

**Status:** PLANNED (Not Implemented)

**Last Updated:** 23 January 2026

**Decision:** To be made after monitoring CPU usage on all-in-one setup
