const { PrismaClient } = require("@prisma/client");
const { PrismaBetterSqlite3 } = require("@prisma/adapter-better-sqlite3");
const crypto = require("crypto");

// Hash password function using native crypto PBKDF2
function hashPassword(password) {
  const salt = crypto.randomBytes(16).toString("hex");
  const hash = crypto.pbkdf2Sync(password, salt, 1000, 64, "sha512").toString("hex");
  return `${salt}:${hash}`;
}

const adapter = new PrismaBetterSqlite3({
  url: "file:./prisma/dev.db"
});
const prisma = new PrismaClient({ adapter });

async function main() {
  console.log("Seeding started...");

  // 1. Clear database
  await prisma.booking.deleteMany();
  await prisma.table.deleteMany();
  await prisma.user.deleteMany();
  await prisma.beer.deleteMany();
  await prisma.promotion.deleteMany();
  await prisma.liveMusic.deleteMany();

  console.log("Database cleared.");

  // 2. Create Users
  const adminPassword = hashPassword("admin123");
  const staffPassword = hashPassword("staff123");

  const admin = await prisma.user.create({
    data: {
      email: "admin@chithole.com",
      passwordHash: adminPassword,
      name: "Admin Boss",
      role: "ADMIN"
    }
  });

  const staff = await prisma.user.create({
    data: {
      email: "staff@chithole.com",
      passwordHash: staffPassword,
      name: "Staff Member",
      role: "STAFF"
    }
  });

  console.log("Users created:", { admin: admin.email, staff: staff.email });

  // 3. Create Tables
  const tablesData = [
    // Indoor Tables
    { number: "T1", zone: "INDOOR", capacity: 2 },
    { number: "T2", zone: "INDOOR", capacity: 2 },
    { number: "T3", zone: "INDOOR", capacity: 4 },
    { number: "T4", zone: "INDOOR", capacity: 4 },
    { number: "T5", zone: "INDOOR", capacity: 4 },
    { number: "T6", zone: "INDOOR", capacity: 6 },
    { number: "T7", zone: "INDOOR", capacity: 6 },
    { number: "T8", zone: "INDOOR", capacity: 6 },
    // Outdoor Tables
    { number: "O1", zone: "OUTDOOR", capacity: 4 },
    { number: "O2", zone: "OUTDOOR", capacity: 4 },
    { number: "O3", zone: "OUTDOOR", capacity: 4 },
    { number: "O4", zone: "OUTDOOR", capacity: 4 },
    // Stage Tables
    { number: "S1", zone: "STAGE", capacity: 4 },
    { number: "S2", zone: "STAGE", capacity: 4 },
    { number: "S3", zone: "STAGE", capacity: 6 },
    { number: "S4", zone: "STAGE", capacity: 8 }
  ];

  for (const t of tablesData) {
    await prisma.table.create({ data: t });
  }
  console.log(`Created ${tablesData.length} tables.`);

  // 4. Create Beers
  const beersData = [
    { tapNumber: "01", name: "Lanna IPA", type: "West Coast IPA", abv: "6.5%", ibu: "60", description: "Piney, citrus, aggressive bite.", price: 220.0 },
    { tapNumber: "02", name: "Moonlight Stout", type: "Imperial Stout", abv: "9.0%", ibu: "40", description: "Dark chocolate, espresso, heavy.", price: 260.0 },
    { tapNumber: "03", name: "Docks Lager", type: "Crisp Lager", abv: "4.5%", ibu: "20", description: "Clean, bready, highly crushable.", price: 180.0 },
    { tapNumber: "04", name: "Rust Belt Red", type: "Red Ale", abv: "5.5%", ibu: "30", description: "Caramel malt sweetness, earthy hops.", price: 200.0 },
    { tapNumber: "05", name: "Gridlock Hazy", type: "NEIPA", abv: "7.2%", ibu: "35", description: "Juice bomb, massive tropical fruit.", price: 240.0 },
    { tapNumber: "06", name: "Iron Porter", type: "Baltic Porter", abv: "8.0%", ibu: "45", description: "Roasted nuts, dark fruit, smooth.", price: 230.0 },
    { tapNumber: "07", name: "Steam Whistle", type: "Steam Beer", abv: "5.0%", ibu: "25", description: "Toasty malt, herbal hops, crisp.", price: 190.0 },
    { tapNumber: "08", name: "Copperhead Amber", type: "Amber Ale", abv: "5.8%", ibu: "28", description: "Malty, stone fruit, smooth finish.", price: 200.0 },
    { tapNumber: "09", name: "Warehouse Sour", type: "Berliner Weisse", abv: "4.2%", ibu: "10", description: "Tart, passion fruit, refreshing.", price: 210.0 },
    { tapNumber: "10", name: "Chiang Mai Wheat", type: "Witbier", abv: "5.2%", ibu: "15", description: "Coriander, orange peel, hazy.", price: 190.0 },
    { tapNumber: "11", name: "Blackout Double", type: "Double IPA", abv: "8.5%", ibu: "80", description: "Resinous, grapefruit, intense.", price: 280.0 },
    { tapNumber: "12", name: "Session Craft", type: "Session Ale", abv: "3.8%", ibu: "18", description: "Light body, floral hops, easy.", price: 170.0 },
    { tapNumber: "13", name: "Smokehouse Porter", type: "Smoked Porter", abv: "6.0%", ibu: "35", description: "Beechwood smoke, coffee, rich.", price: 220.0 },
    { tapNumber: "14", name: "Neon Gold", type: "Golden Ale", abv: "4.8%", ibu: "22", description: "Crisp, honey notes, clean.", price: 180.0 },
    { tapNumber: "15", name: "Pipeline IPA", type: "Hazy IPA", abv: "6.8%", ibu: "55", description: "Citrus peel, stone fruit, soft.", price: 230.0 },
    { tapNumber: "16", name: "Nightshift Stout", type: "Milk Stout", abv: "7.5%", ibu: "42", description: "Sweet, creamy, vanilla notes.", price: 240.0 }
  ];

  for (const b of beersData) {
    await prisma.beer.create({ data: b });
  }
  console.log(`Created ${beersData.length} beers.`);

  // 5. Create Promotions
  const promosData = [
    {
      title: "Happy Hour: Buy 1 Get 1",
      offer: "BUY 1 GET 1",
      period: "Daily • 5PM - 7PM",
      description: "Double the impact. Buy any pint from our selected industrial tap list and receive a second on the house. Fuel the evening shift.",
      image: "https://lh3.googleusercontent.com/aida-public/AB6AXuDHwA8KcGYMr2VLkmLLnjECHvqFbfdu3zG0ci687pgRafHiOZbGjn23NwedJioVSrE7xkQpGYuCnqfVm_hDfz-c4NI1N8QzOyBPrOYh0YVMhuk9viUdIkV0Riwz8BdoPs9LAIKLfN5A17eGtUWtrvuknenPbaAkOgNJtmV_OecDwrLehmwDljq7t-xxZetPXa_rGgG2L6eaT6ouVVQwzySk_cPXRPa4zoxjvD0WWLdDTgtzdWpD3CIhl18EyBOhY7VuSE5O7YEpSHA",
      active: true
    },
    {
      title: "Craft Night",
      offer: "15% OFF",
      period: "Every Wednesday",
      description: "A gathering for the enthusiasts. Flash your brewing guild card or demonstrate your palate to receive 15% off all tasting flights.",
      image: "https://lh3.googleusercontent.com/aida-public/AB6AXuCjuC4Rhe_cyM79AQ9Fpb_WOChhFqNFm8iNMSNHZmUDWkr6iPeW4_ejfVChfviUJRFr3UOVHPhGYHpMXtGGYp7mqV8Gr19slMZrh06VNvBitrNindp1bgIXLx3S0w2gy5jJQ7E_DXAvUzt5u6qtb0KRwbsqVzQBYEhTMvFyGzvtGeK7CeGZXGJRtq60lo4M8V8RM8yvbhu8NFIEBMdRTHXf40curhNxl0lE6w5jAZAACwXO9Fg8PBL19CJbojpVNbKqRov-MAGhUJ8",
      active: true
    },
    {
      title: "Lanna IPA Tasting",
      offer: "Exclusive Release",
      period: "Limited Batch",
      description: "The newest prototype from Warehouse B. A highly aromatic, brutally bitter IPA engineered for maximum flavor impact. Limited batch.",
      image: "https://lh3.googleusercontent.com/aida-public/AB6AXuDcVxA5cJov1vl9r7VHzgxi_ztVk6R09tWs2WQ0aD9oeLwzt74HPAEofuig6X8OnhLofwD4wzuVbAr5NxqpwjkUotr-Pbstae6O-49Np7sG6itLVvm5DtKsrK7r9p_KumGetOrqRT0FpkmL8fHO0H0SXwVDUTWOtp1-vNvEVScw7HDtXBEaYDAOsqjiXFIj2MnXpX9kAf2c9YRuzToe0kgVrlvuZa5nSg3a4alBshuDBI1vPTUw0mZDEYmxpizBCFHsA6TAcunCl_A",
      active: true
    }
  ];

  for (const p of promosData) {
    await prisma.promotion.create({ data: p });
  }
  console.log(`Created ${promosData.length} promotions.`);

  // 6. Create Live Music Schedule
  const musicData = [
    { day: "Mon", time: "8:00 PM", artist: "Acoustic Soul", description: "Stripped down R&B and soul covers. Low key vibes to start the week.", status: "REGULAR" },
    { day: "Wed", time: "9:00 PM", artist: "Jazz & Hops", description: "Experimental jazz quartets from the local conservatory. High energy, unpredictable.", status: "REGULAR" },
    { day: "Fri", time: "9:30 PM", artist: "Rock Unplugged", description: "Heavy hitters stripping down their sets. Loud even when it's quiet.", status: "HOT" },
    { day: "Sat", time: "9:30 PM", artist: "Local Indie Showcase", description: "Three bands, 45-minute sets. Discover the underground scene.", status: "REGULAR" },
    { day: "Sun", time: "4:00 PM", artist: "Hangover Sessions", description: "Bluegrass, folk, and hair of the dog. Afternoon sets to ease into the week.", status: "DAYSET" }
  ];

  for (const m of musicData) {
    await prisma.liveMusic.create({ data: m });
  }
  console.log(`Created ${musicData.length} live music events.`);

  console.log("Seeding completed successfully!");
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
