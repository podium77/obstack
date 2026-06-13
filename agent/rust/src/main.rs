use clap::Parser;

/// Minimal obsagent placeholder
#[derive(Parser)]
struct Args {
    /// Run in foreground
    #[arg(long)]
    foreground: bool,
}

fn main() {
    let _ = Args::parse();
    println!("obsagent minimal binary — runtime placeholder");
}
