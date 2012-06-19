package com.github.rpository;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.OutputStream;
import java.sql.ResultSet;
import java.sql.SQLException;

import org.apache.commons.compress.archivers.ArchiveOutputStream;
import org.apache.commons.compress.archivers.ArchiveStreamFactory;
import org.apache.commons.compress.archivers.tar.TarArchiveOutputStream;
import org.apache.commons.compress.compressors.gzip.GzipCompressorOutputStream;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.core.RowCallbackHandler;
import org.springframework.jdbc.datasource.DriverManagerDataSource;

public class Packager {
	private final String articleStmt = "SELECT published_articles.issue_id, published_articles.date_published, "
			+ "S1.setting_value, S2.setting_value" + "FROM published_articles"
			+ "JOIN article_settings S1 ON published_articles.article_id = S1.article_id"
			+ "JOIN article_settings S2 ON published_articles.article_id = S2.article_id"
			+ "WHERE S1.setting_name = \"title\" AND S2.setting_name = \"abstract\""
			+ "AND published_articles.article_id = ?" + "LIMIT 1";
	private final String authorsStmt = "SELECT authors.author_id, authors.primary_contact, authors.seq,"
			+ "authors.first_name, authors.middle_name, authors.last_name, authors.country, authors.email"
			+ "FROM published_articles" + "JOIN authors ON published_articles.article_id = authors.submission_id"
			+ "WHERE published_articles.article_id = ?" + "ORDER BY authors.seq;";
	private final String filesStmt = "SELECT F.file_name, F.original_file_name, F.type" + "FROM article_files F"
			+ "WHERE F.article_id = ?";

	private final JdbcTemplate db;
	private final String repoPath;
	private final String filesPath;

	public Packager(JdbcTemplate db, String repoPath, String filesPath) {
		this.db = db;
		this.repoPath = repoPath;
		this.filesPath = filesPath;
	}

	public boolean writeRpackage(int article_id) throws IOException, SQLException {
		/*
		 * set("Package", pkg); set("Version", version); set("License", license); set("Description", descr);
		 * set("Title", title); set("Author", author); set("Maintainer", maintainer);
		 * 
		 * 
		 * Date
		 */
		final String suppPath = filesPath + "/" + article_id + "/supp/";
		final String preprPath = filesPath + "/" + article_id + "/public/";
		final Object[] args = { new Integer(article_id) };
		final PackageDescription pd = new PackageDescription();
		final StringBuilder authors = new StringBuilder("c(");
		final StringBuilder pkgName = new StringBuilder();
		final OutputStream fos;
		final ArchiveOutputStream os;

		pd.set("Repository", "PMR2");
		pd.set("Depends", "R (>= 2.14)");

		db.query(articleStmt, args, new RowCallbackHandler() {
			public void processRow(ResultSet rs) throws SQLException {
				pd.set("Date", rs.getString(2).split("")[1]);
				pd.set("Title", rs.getString(3));
				pd.set("Description", rs.getString(4));
			}
		});

		db.query(authorsStmt, args, new RowCallbackHandler() {
			public void processRow(ResultSet rs) throws SQLException {
				authors.append("person(");
				authors.append("given = \"").append(rs.getString(4)).append("\", family = \"").append(rs.getString(5))
						.append("\"");
				pkgName.append(rs.getString(5));
				if (rs.getString(5) != null) {
					authors.append(", middle = \"").append(rs.getString(5)).append("\"");
				}
				if (rs.getString(8) != null) {
					authors.append(", email = \"").append(rs.getString(5)).append("\"");
				}

				authors.append(",role = c(\"aut\"");
				if (rs.getInt(2) == 1) {
					// primary_contact
					authors.append(", \"cre\"");
				}
				authors.append("))");
			}
		});
		pkgName.append(pd.get("Date").split("-", 1)[0]);
		pd.set("Package", pkgName.toString());

		fos = new FileOutputStream(repoPath + "/" + pkgName + "_1.0.tar.gz");
		os = new TarArchiveOutputStream(new GzipCompressorOutputStream(fos));
		pkgName.append("/");

		final PackageWriter pw = new PackageWriter(os, new ArchiveEntryFactory(ArchiveStreamFactory.TAR), pd,
				pkgName.toString());

		db.query(filesStmt, args, new RowCallbackHandler() {
			public void processRow(ResultSet rs) throws SQLException {
				final String name = rs.getString(1);
				final String origName = rs.getString(2);
				final String type = rs.getString(3);
				try {
					if (type == "supp") {
						pw.appendLegacy(new File(suppPath + name), origName);
					} else if (type == "public") {
						pw.appendPreprint(new File(preprPath + name), origName);
					}
				} catch (IOException ioe) {
					throw new SQLException(ioe);
				}

			}
		});
		pw.close();
		return true;
	}

	public static int main(String[] argv) {
		if (argv.length < 3) {
			System.err.println("Usage: PROGRAM R-Repository-Path Suppl-Files-Path [article_ids]");
		}
		final DriverManagerDataSource ds = new DriverManagerDataSource("jdbc:mysql://localhost:3306/ojs", "ojs",
				"admin");
		final JdbcTemplate db = new JdbcTemplate(ds);
		final Packager p = new Packager(db, argv[0], argv[1]);
		for (int i = 2; i < argv.length; ++i) {
			try {
				p.writeRpackage(Integer.parseInt(argv[i]));
			} catch (NumberFormatException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			} catch (IOException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			} catch (SQLException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
		return 0;
	}
}
